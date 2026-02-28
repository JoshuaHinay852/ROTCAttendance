<?php
/**
 * Shared event status transition logic.
 * Automatic flow:
 * - scheduled: before start datetime
 * - ongoing:   start <= now < end
 * - completed: now >= end datetime
 *
 * Cancelled events are intentionally excluded from auto-transitions.
 */

if (!function_exists('getAutomaticEventStatus')) {
    function getAutomaticEventStatus($event_date, $start_time, $end_time, $now_datetime = null) {
        $current_timestamp = $now_datetime ? strtotime($now_datetime) : time();
        $start_timestamp = strtotime($event_date . ' ' . $start_time);
        $end_timestamp = strtotime($event_date . ' ' . $end_time);

        // Overnight event support: end time is on the next day.
        if ($end_timestamp <= $start_timestamp) {
            $end_timestamp = strtotime('+1 day', $end_timestamp);
        }

        if ($current_timestamp >= $end_timestamp) {
            return 'completed';
        }

        if ($current_timestamp >= $start_timestamp) {
            return 'ongoing';
        }

        return 'scheduled';
    }
}

if (!function_exists('updateEventStatusesAdvanced')) {
    function updateEventStatusesAdvanced(PDO $pdo) {
        try {
            $pdo->beginTransaction();

            $start_datetime_expr = "TIMESTAMP(event_date, start_time)";
            $end_datetime_expr = "CASE
                                    WHEN end_time >= start_time THEN TIMESTAMP(event_date, end_time)
                                    ELSE DATE_ADD(TIMESTAMP(event_date, end_time), INTERVAL 1 DAY)
                                  END";
            $deleted_filter = '';

            // Support both schemas (with or without soft-delete columns).
            static $has_deleted_at = null;
            if ($has_deleted_at === null) {
                try {
                    $column_check = $pdo->query("SHOW COLUMNS FROM events LIKE 'deleted_at'");
                    $has_deleted_at = ($column_check && $column_check->fetch()) ? true : false;
                } catch (Exception $e) {
                    $has_deleted_at = false;
                }
            }

            if ($has_deleted_at) {
                $deleted_filter = "AND deleted_at IS NULL";
            }

            // 1) completed: now >= end
            $stmt = $pdo->prepare("
                UPDATE events
                SET status = 'completed', updated_at = NOW()
                WHERE status != 'cancelled'
                  AND status != 'completed'
                  $deleted_filter
                  AND NOW() >= ($end_datetime_expr)
            ");
            $stmt->execute();
            $completed_updates = $stmt->rowCount();

            // 2) ongoing: start <= now < end
            $stmt = $pdo->prepare("
                UPDATE events
                SET status = 'ongoing', updated_at = NOW()
                WHERE status != 'cancelled'
                  AND status != 'ongoing'
                  $deleted_filter
                  AND NOW() >= ($start_datetime_expr)
                  AND NOW() < ($end_datetime_expr)
            ");
            $stmt->execute();
            $ongoing_updates = $stmt->rowCount();

            // 3) scheduled: now < start
            $stmt = $pdo->prepare("
                UPDATE events
                SET status = 'scheduled', updated_at = NOW()
                WHERE status != 'cancelled'
                  AND status != 'scheduled'
                  $deleted_filter
                  AND NOW() < ($start_datetime_expr)
            ");
            $stmt->execute();
            $scheduled_updates = $stmt->rowCount();

            $pdo->commit();

            return [
                'success' => true,
                'ongoing' => $ongoing_updates,
                'completed' => $completed_updates,
                'scheduled' => $scheduled_updates
            ];
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            error_log("Error in event auto-status update: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
}

if (!function_exists('ensureEventStatusesAreCurrent')) {
    function ensureEventStatusesAreCurrent(PDO $pdo, $force = false) {
        static $already_updated = false;
        static $last_result = [
            'success' => true,
            'ongoing' => 0,
            'completed' => 0,
            'scheduled' => 0
        ];

        if ($already_updated && !$force) {
            return $last_result;
        }

        $already_updated = true;
        $last_result = updateEventStatusesAdvanced($pdo);
        return $last_result;
    }
}
