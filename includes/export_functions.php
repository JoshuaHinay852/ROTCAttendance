<?php
// export_functions.php - Pure PHP implementation, no external libraries needed

function exportData($data, $headers, $filename, $format) {
    switch ($format) {
        case 'csv':
            exportCSV($data, $headers, $filename);
            break;
        case 'excel':
            exportExcel($data, $headers, $filename);
            break;
        case 'pdf':
            exportPDF($data, $headers, $filename);
            break;
        default:
            return false;
    }
    return true;
}

function exportCSV($data, $headers, $filename) {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '.csv"');
    header('Cache-Control: no-cache, no-store, must-revalidate');
    header('Pragma: no-cache');
    header('Expires: 0');
    
    $output = fopen('php://output', 'w');
    
    // Add UTF-8 BOM for Excel compatibility
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    // Add headers
    fputcsv($output, $headers);
    
    // Add data
    foreach ($data as $row) {
        fputcsv($output, $row);
    }
    
    fclose($output);
    exit();
}

function exportExcel($data, $headers, $filename) {
    // Generate HTML table with Excel-compatible format
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment; filename="' . $filename . '.xls"');
    header('Cache-Control: no-cache, no-store, must-revalidate');
    header('Pragma: no-cache');
    header('Expires: 0');
    
    echo '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">';
    echo '<head>';
    echo '<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">';
    echo '<!--[if gte mso 9]><xml><x:ExcelWorkbook><x:ExcelWorksheets><x:ExcelWorksheet><x:Name>Sheet1</x:Name></x:ExcelWorksheet></x:ExcelWorksheets></x:ExcelWorkbook></xml><![endif]-->';
    echo '<style>';
    echo 'td { border: 1px solid #000; }';
    echo 'th { background: #4f46e5; color: #fff; border: 1px solid #000; }';
    echo '</style>';
    echo '</head>';
    echo '<body>';
    echo '<table border="1">';
    
    // Headers
    echo '<thead><tr>';
    foreach ($headers as $header) {
        echo '<th>' . htmlspecialchars($header) . '</th>';
    }
    echo '</tr></thead>';
    
    // Data
    echo '<tbody>';
    foreach ($data as $row) {
        echo '<tr>';
        foreach ($row as $cell) {
            echo '<td>' . htmlspecialchars($cell) . '</td>';
        }
        echo '</tr>';
    }
    echo '</tbody>';
    echo '</table>';
    echo '</body></html>';
    exit();
}

function exportPDF($data, $headers, $filename) {
    // Create PDF using HTML2PDF approach (no external library)
    $html = '
    <!DOCTYPE html>
    <html>
    <head>
        <title>Attendance Report</title>
        <style>
            body { 
                font-family: Arial, sans-serif; 
                margin: 20px;
                line-height: 1.4;
            }
            h1 { 
                color: #333; 
                text-align: center; 
                margin-bottom: 5px;
                font-size: 24px;
            }
            .subtitle {
                text-align: center;
                color: #666;
                font-size: 14px;
                margin-bottom: 20px;
            }
            table { 
                width: 100%; 
                border-collapse: collapse; 
                margin-top: 20px; 
                font-size: 11px;
            }
            th { 
                background: #4f46e5; 
                color: white; 
                padding: 8px; 
                text-align: left; 
                font-weight: bold;
                font-size: 12px;
            }
            td { 
                padding: 6px; 
                border: 1px solid #ddd; 
            }
            tr:nth-child(even) { 
                background: #f9f9f9; 
            }
            .header { 
                text-align: center; 
                margin-bottom: 20px; 
                border-bottom: 2px solid #4f46e5;
                padding-bottom: 10px;
            }
            .date { 
                color: #666; 
                font-size: 12px; 
            }
            .summary {
                background: #f3f4f6;
                padding: 10px;
                border-radius: 5px;
                margin: 10px 0;
            }
            .footer {
                margin-top: 20px;
                font-size: 10px;
                color: #999;
                text-align: center;
                position: fixed;
                bottom: 10px;
                width: 100%;
            }
            @media print {
                .footer {
                    position: fixed;
                    bottom: 10px;
                }
            }
        </style>
    </head>
    <body>
        <div class="header">
            <h1>ROTC Unit - Attendance Report</h1>
            <p class="subtitle">BISU Balilihan</p>
            <p class="date">Generated on: ' . date('F j, Y g:i A') . '</p>
        </div>
        
        <div class="summary">
            <strong>Summary:</strong> Total Records: ' . count($data) . '
        </div>
        
        <table>
            <thead>
                <tr>';
    
    foreach ($headers as $header) {
        $html .= '<th>' . htmlspecialchars($header) . '</th>';
    }
    
    $html .= '
                </tr>
            </thead>
            <tbody>';
    
    foreach ($data as $row) {
        $html .= '<tr>';
        foreach ($row as $cell) {
            $html .= '<td>' . htmlspecialchars($cell) . '</td>';
        }
        $html .= '</tr>';
    }
    
    $html .= '
            </tbody>
        </table>
        
        <div class="footer">
            <p>This is a system-generated report. For official use only.</p>
            <p>Generated by ROTC Management System</p>
        </div>
    </body>
    </html>';
    
    // For PDF, we'll use mPDF if available, otherwise output HTML for browser printing
    if (function_exists('exec') && file_exists('../vendor/autoload.php')) {
        require_once '../vendor/autoload.php';
        
        if (class_exists('Mpdf\Mpdf')) {
            $mpdf = new \Mpdf\Mpdf([
                'mode' => 'utf-8',
                'format' => 'A4-L',
                'margin_left' => 10,
                'margin_right' => 10,
                'margin_top' => 20,
                'margin_bottom' => 20,
                'margin_header' => 5,
                'margin_footer' => 5
            ]);
            
            $mpdf->WriteHTML($html);
            $mpdf->Output($filename . '.pdf', 'D');
            exit();
        }
    }
    
    // Fallback: Output HTML for browser printing
    header('Content-Type: text/html');
    echo $html;
    echo '<script>window.onload = function() { window.print(); }</script>';
    exit();
}

// Simple function to generate Excel using only PHP
function generateSimpleExcel($data, $headers, $filename) {
    $xml = '<?xml version="1.0" encoding="UTF-8"?>
    <?mso-application progid="Excel.Sheet"?>
    <Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet"
              xmlns:o="urn:schemas-microsoft-com:office:office"
              xmlns:x="urn:schemas-microsoft-com:office:excel"
              xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet"
              xmlns:html="http://www.w3.org/TR/REC-html40">
        <Worksheet ss:Name="Sheet1">
            <Table>';
    
    // Headers
    $xml .= '<Row>';
    foreach ($headers as $header) {
        $xml .= '<Cell><Data ss:Type="String">' . htmlspecialchars($header) . '</Data></Cell>';
    }
    $xml .= '</Row>';
    
    // Data
    foreach ($data as $row) {
        $xml .= '<Row>';
        foreach ($row as $cell) {
            $xml .= '<Cell><Data ss:Type="String">' . htmlspecialchars($cell) . '</Data></Cell>';
        }
        $xml .= '</Row>';
    }
    
    $xml .= '    </Table>
        </Worksheet>
    </Workbook>';
    
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment; filename="' . $filename . '.xls"');
    header('Cache-Control: max-age=0');
    
    echo $xml;
    exit();
}