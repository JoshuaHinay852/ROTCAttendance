// Event Monitor - Real-time status updates
class EventMonitor {
    constructor() {
        this.checkInterval = 60000; // Check every minute
        this.init();
    }
    
    init() {
        this.checkEventStatus();
        setInterval(() => this.checkEventStatus(), this.checkInterval);
    }
    
    checkEventStatus() {
        fetch('check_event_status.php?ajax=1')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    this.updateUI(data);
                }
            })
            .catch(error => console.error('Event monitor error:', error));
    }
    
    updateUI(data) {
        // Update status badges if they changed
        const statusBadges = document.querySelectorAll('[data-event-status]');
        statusBadges.forEach(badge => {
            const eventId = badge.dataset.eventId;
            // You can implement specific event status updates here
        });
        
        // Show notification if there are updates
        if (data.status_updates.ongoing > 0 || data.status_updates.completed > 0) {
            this.showNotification(data);
        }
    }
    
    showNotification(data) {
        const updates = [];
        if (data.status_updates.ongoing > 0) updates.push(`${data.status_updates.ongoing} event(s) started`);
        if (data.status_updates.completed > 0) updates.push(`${data.status_updates.completed} event(s) completed`);
        if (data.late_updates > 0) updates.push(`${data.late_updates} cadet(s) marked as late`);
        
        if (updates.length > 0) {
            // You can implement a toast notification here
            console.log('Event updates:', updates.join(', '));
        }
    }
}

// Initialize when page loads
document.addEventListener('DOMContentLoaded', () => {
    new EventMonitor();
});