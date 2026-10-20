<?php

/*
|--------------------------------------------------------------------------
| Excel download
|--------------------------------------------------------------------------
| sendExcel('Seeding BU15.xlsx', [...]) builds the .xlsx with
| ../rankings/make_excel.py (openpyxl - PHP's zip extension is off in
| XAMPP) and sends it to the browser as a download. See make_excel.py for
| the keys: sheet, title, info, headers, rows, widths.
*/

function sendExcel($fileName, array $report)
{
    $pythonPath = 'C:\\Users\\user\\.pyenv\\pyenv-win\\versions\\3.13.5\\python.exe';
    $script = realpath(__DIR__ . '/../rankings/make_excel.py');

    if (!is_file($pythonPath) || !$script) {
        http_response_code(500);
        die('The Excel export is not set up (Python or rankings/make_excel.py not found).');
    }

    $output = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'report_' . bin2hex(random_bytes(8)) . '.xlsx';

    $process = proc_open(
        [$pythonPath, $script, $output],
        [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
        $pipes
    );

    if (!is_resource($process)) {
        http_response_code(500);
        die('The Excel export could not be started.');
    }

    fwrite($pipes[0], json_encode($report));
    fclose($pipes[0]);

    stream_get_contents($pipes[1]);
    $errors = stream_get_contents($pipes[2]);

    fclose($pipes[1]);
    fclose($pipes[2]);
    proc_close($process);

    if (!is_file($output)) {
        http_response_code(500);
        die('The Excel file could not be made. ' . htmlspecialchars(trim(strtok($errors, "\n"))));
    }

    // Characters Windows doesn't allow in file names
    $fileName = preg_replace('/[\\\\\/:*?"<>|]+/', ' ', $fileName);

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $fileName . '"; filename*=UTF-8\'\'' . rawurlencode($fileName));
    header('Content-Length: ' . filesize($output));
    header('Cache-Control: no-store');

    readfile($output);
    @unlink($output);
    exit;
}
