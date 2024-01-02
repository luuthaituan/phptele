<?php
require 'vendor/autoload.php';

use phpseclib3\Net\SSH2;
use phpseclib3\Net\SFTP;
use GuzzleHttp\Client;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

// Thông tin kết nối SSH
$sshHost = '192.168.241.2';
$sshPort = 22;
$sshUsername = 'thaituan';
$sshPassword = 'Tuan@8999';

// Thông tin kết nối đến MySQL
$mysqlHost = '192.168.241.2';
$mysqlPort = 3306;
$mysqlDatabase = 'local';
$mysqlUsername = 'thaituan';
$mysqlPassword = 'Tuan@8999';

// Câu truy vấn bạn muốn thực hiện
$query = "SELECT * FROM local.test;";

// Khởi tạo biến
$dbConnection = null;
$ssh = null;
$result = null;

try {
    // Kết nối đến server SSH
    $ssh = new SSH2($sshHost, $sshPort);
    if (!$ssh->login($sshUsername, $sshPassword)) {
        throw new Exception('Không thể đăng nhập SSH.');
    }

    // Bật chế độ PTY
    $ssh->enablePTY();

    // Kết nối đến MySQL thông qua SSH
    $dbConnection = new PDO(
        "mysql:host={$mysqlHost};port={$mysqlPort};dbname={$mysqlDatabase}",
        $mysqlUsername,
        $mysqlPassword,
        array(PDO::ATTR_PERSISTENT => true, PDO::MYSQL_ATTR_LOCAL_INFILE => true, PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8;")
    );

    // Thiết lập thuộc tính để nhận thông báo lỗi
    $dbConnection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Thực hiện truy vấn
    $result = $dbConnection->query($query)->fetchAll(PDO::FETCH_ASSOC);

    // Gửi kết quả về trình duyệt
    if (count($result) > 0) {
        // Hiển thị kết quả dưới dạng bảng
        echo '<div class="alert alert-success mt-3" role="alert">';
        echo '<h5>Query result:</h5>';
        echo '<table class="table table-bordered custom-table">';
        echo '<thead class="thead-dark">';
        echo '<tr>';
        foreach ($result[0] as $key => $value) {
            echo '<th>' . htmlspecialchars($key) . '</th>';
        }
        echo '</tr>';
        echo '</thead>';
        echo '<tbody>';
        foreach ($result as $row) {
            echo '<tr>';
            foreach ($row as $value) {
                echo '<td>' . htmlspecialchars($value) . '</td>';
            }
            echo '</tr>';
        }
        echo '</tbody>';
        echo '</table>';
        echo '</div>';

        // Xuất ra file Excel và gửi chi tiết kết quả về Telegram
        $excelFilePath = generateExcelFile($result, 'query_result.xlsx');
        sendTelegramDocument($excelFilePath);
    } else {
        // Gửi thông báo không có dữ liệu về Telegram
        sendTelegramMessage('No data found in the database.');

        // Hiển thị thông báo trên trình duyệt
        echo '<div class="alert alert-info mt-3" role="alert">';
        echo 'No data found in the database.';
        echo '</div>';
    }
} catch (Exception $e) {
    // Xử lý lỗi và gửi về trình duyệt
    echo '<div class="alert alert-danger mt-3" role="alert">';
    echo 'Error checking database: ' . $e->getMessage();
    echo '</div>';
} finally {
    // Đóng kết nối
    if ($dbConnection) {
        $dbConnection = null;
    }

    // Đóng kết nối SSH
    if ($ssh) {
        $ssh->disconnect();
    }
}

// Hàm gửi thông báo về Telegram
function sendTelegramMessage($message)
{
    // Thay thế YOUR_BOT_TOKEN và YOUR_CHAT_ID bằng thông tin của bot và chat của bạn
    $botToken = '6531445104:AAFviAc-HUb2LQ0hL0qX_mEu3NvSonO2D8k';
    $chatId = '2065288078';

    // Tạo URL để gửi tin nhắn
    $url = "https://api.telegram.org/bot{$botToken}/sendMessage";
    
    // Dữ liệu để gửi
    $data = [
        'chat_id' => $chatId,
        'text' => $message,
    ];

    // Sử dụng GuzzleHTTP để gửi HTTP POST request
    $client = new Client();
    $client->post($url, ['json' => $data]);
}

// Hàm gửi tài liệu về Telegram
function sendTelegramDocument($filePath)
{
    $botToken = '6531445104:AAFviAc-HUb2LQ0hL0qX_mEu3NvSonO2D8k';
    $chatId = '2065288078';

    // Tạo URL để gửi tài liệu
    $url = "https://api.telegram.org/bot{$botToken}/sendDocument";

    // Sử dụng GuzzleHTTP để gửi HTTP POST request
    $client = new Client();
    $client->request('POST', $url, [
        'multipart' => [
            [
                'name' => 'chat_id',
                'contents' => $chatId,
            ],
            [
                'name' => 'document',
                'contents' => fopen($filePath, 'r'),
                'filename' => basename($filePath),
            ],
        ],
    ]);
}

// Hàm tạo file Excel từ kết quả
function generateExcelFile($data, $fileName)
{
    // Tạo đối tượng Spreadsheet
    $spreadsheet = new Spreadsheet();

    // Tạo sheet mới
    $sheet = $spreadsheet->getActiveSheet();

    // Ghi dữ liệu từ mảng vào sheet
    $rowIndex = 1;
    foreach ($data as $row) {
        $colIndex = 1;
        foreach ($row as $value) {
            $sheet->setCellValueByColumnAndRow($colIndex, $rowIndex, $value);
            $colIndex++;
        }
        $rowIndex++;
    }

    // Tạo writer để ghi vào file Excel
    $writer = new Xlsx($spreadsheet);

    // Lưu file Excel
    $excelFilePath = __DIR__ . '/' . $fileName;
    $writer->save($excelFilePath);

    return $excelFilePath;
}
?>
