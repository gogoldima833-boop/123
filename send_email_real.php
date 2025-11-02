<?php
// ВЕРСИЯ БЕЗ ДАННЫХ ПОЛЬЗОВАТЕЛЯ В ПИСЬМЕ ДЛЯ НЕГО
header('Content-Type: application/json; charset=utf-8');
header("Access-Control-Allow-Origin: *");

error_reporting(E_ALL);
ini_set('display_errors', 0);

function sendJsonResponse($success, $message, $data = []) {
    $response = ['success' => $success, 'message' => $message];
    if (!empty($data)) {
        $response['data'] = $data;
    }
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJsonResponse(false, 'Метод не разрешен');
}

$name = trim($_POST['name'] ?? '');
$email = trim($_POST['email'] ?? '');
$phone = trim($_POST['phone'] ?? '');
$message_text = trim($_POST['message'] ?? '');

if (empty($name) || empty($email)) {
    sendJsonResponse(false, 'Заполните имя и email');
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    sendJsonResponse(false, 'Некорректный email адрес');
}

try {
    $pdf_filename = 'document.pdf';
    $pdf_path = __DIR__ . '/' . $pdf_filename;
    
    if (!file_exists($pdf_path)) {
        sendJsonResponse(false, 'PDF файл не найден');
    }

    require_once 'PHPMailer/src/PHPMailer.php';
    require_once 'PHPMailer/src/SMTP.php';
    require_once 'PHPMailer/src/Exception.php';

    $mail = new PHPMailer\PHPMailer\PHPMailer(true);

    // ⚠️ НАСТРОЙКИ GMAIL - ЗАМЕНИТЕ НА СВОИ! ⚠️
    $gmail_email = 'gogoldima833@gmail.com';        // Ваш Gmail (для отправки)
    $gmail_password = 'bnve optq losz epkf';   // Пароль приложения
    $from_name = 'Название Вашего Сайта';        // Имя отправителя
    $admin_email = 'gogoldima833@gmail.com';        // ⚡ ВАШ email для уведомлений

    // Настройки SMTP
    $mail->isSMTP();
    $mail->Host = 'smtp.gmail.com';
    $mail->SMTPAuth = true;
    $mail->Username = $gmail_email;
    $mail->Password = $gmail_password;
    $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port = 587;
    $mail->SMTPDebug = 0;

    // Настройки кодировки
    $mail->CharSet = 'UTF-8';
    $mail->Encoding = 'base64';
    
    // =========================================================================
    // 1. ОТПРАВКА PDF ПОЛЬЗОВАТЕЛЮ (БЕЗ ЕГО ДАННЫХ)
    // =========================================================================
    
    $mail->setFrom($gmail_email, $from_name);
    $mail->addAddress($email, $name); // PDF отправляем пользователю
    $mail->addReplyTo($gmail_email, $from_name);
    
    // Тема и тело для пользователя
    $mail->Subject = '=?UTF-8?B?' . base64_encode('Ваш PDF файл с сайта') . '?=';
    
    // ⚡ ПИСЬМО ДЛЯ ПОЛЬЗОВАТЕЛЯ - БЕЗ его телефона и сообщения
    $user_email_body = "
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset=\"UTF-8\">
        <style>
            body { font-family: Arial, sans-serif; color: #333; line-height: 1.6; }
            .header { color: #2c3e50; font-size: 18px; margin-bottom: 20px; }
            .message { background: #f8f9fa; padding: 20px; border-radius: 5px; margin: 15px 0; }
            .footer { margin-top: 20px; padding-top: 20px; border-top: 1px solid #ddd; color: #7f8c8d; }
            .pdf-icon { color: #e74c3c; font-size: 20px; margin-right: 10px; }
        </style>
    </head>
    <body>
        <div class=\"header\">Здравствуйте, <strong>{$name}</strong>!</div>
        
        <div class=\"message\">
            <p><span class=\"pdf-icon\">📎</span> <strong>Ваш PDF файл готов!</strong></p>
            <p>Благодарим вас за обратную связь на нашем сайте. Во вложении этого письма вы найдете запрошенный PDF файл.</p>
        </div>
        
        <p>Если у вас возникли вопросы, вы всегда можете ответить на это письмо.</p>
        
        <div class=\"footer\">
            <p>---<br>
            <strong>С уважением,</strong><br>
            {$from_name}<br>
            " . date('d.m.Y H:i') . "
            </p>
        </div>
    </body>
    </html>
    ";
    
    $mail->IsHTML(true);
    $mail->Body = $user_email_body;
    
    // Текстовая версия для пользователя (тоже без данных)
    $mail->AltBody = "Здравствуйте, {$name}!\r\n\r\n" .
                    "Ваш PDF файл готов!\r\n\r\n" .
                    "Благодарим вас за обратную связь на нашем сайте. " .
                    "Во вложении этого письма вы найдете запрошенный PDF файл.\r\n\r\n" .
                    "Если у вас возникли вопросы, вы всегда можете ответить на это письмо.\r\n\r\n" .
                    "---\r\nС уважением,\r\n{$from_name}\r\n" . date('d.m.Y H:i');

    // Добавляем PDF для пользователя
    $mail->addAttachment($pdf_path, $pdf_filename);

    // Отправляем письмо пользователю
    $user_email_sent = $mail->send();
    
    if (!$user_email_sent) {
        sendJsonResponse(false, '❌ Ошибка отправки PDF пользователю: ' . $mail->ErrorInfo);
    }

    // =========================================================================
    // 2. ОТПРАВКА УВЕДОМЛЕНИЯ АДМИНУ (ВАМ) - ЗДЕСЬ ВСЕ ДАННЫЕ
    // =========================================================================
    
    // Очищаем предыдущие настройки
    $mail->clearAddresses();
    $mail->clearAttachments();
    $mail->clearReplyTos();

    // Настраиваем письмо для админа
    $mail->setFrom($gmail_email, $from_name);
    $mail->addAddress($admin_email); // Уведомление отправляем вам
    $mail->addReplyTo($email, $name); // Ответ на это письмо пойдет пользователю
    
    // Тема для админа
    $mail->Subject = '=?UTF-8?B?' . base64_encode('📥 Новая заявка на PDF') . '?=';
    
    // Тело письма для админа - ЗДЕСЬ ВСЕ ДАННЫЕ ПОЛЬЗОВАТЕЛЯ
    $admin_email_body = "
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset=\"UTF-8\">
        <style>
            body { font-family: Arial, sans-serif; color: #333; line-height: 1.6; }
            .header { background: #2c3e50; color: white; padding: 20px; border-radius: 5px; }
            .info-block { background: #f8f9fa; padding: 15px; margin: 10px 0; border-radius: 5px; border-left: 4px solid #3498db; }
            .label { font-weight: bold; color: #2c3e50; }
            .footer { margin-top: 20px; padding-top: 20px; border-top: 1px solid #ddd; color: #7f8c8d; font-size: 14px; }
        </style>
    </head>
    <body>
        <div class=\"header\">
            <h2>📥 Новая заявка на сайте</h2>
            <p>Пользователь запросил PDF файл</p>
        </div>
        
        <div class=\"info-block\">
            <div class=\"label\">👤 Имя:</div>
            <div>{$name}</div>
        </div>
        
        <div class=\"info-block\">
            <div class=\"label\">📧 Email:</div>
            <div><a href=\"mailto:{$email}\">{$email}</a></div>
        </div>
    ";
    
    if (!empty($phone)) {
        $admin_email_body .= "
        <div class=\"info-block\">
            <div class=\"label\">📞 Телефон:</div>
            <div><a href=\"tel:{$phone}\">{$phone}</a></div>
        </div>
        ";
    }
    
    if (!empty($message_text)) {
        $admin_email_body .= "
        <div class=\"info-block\">
            <div class=\"label\">💬 Сообщение:</div>
            <div>{$message_text}</div>
        </div>
        ";
    }
    
    $admin_email_body .= "
        <div class=\"info-block\">
            <div class=\"label\">📎 Отправленный файл:</div>
            <div>{$pdf_filename}</div>
        </div>
        
        <div class=\"info-block\">
            <div class=\"label\">🕐 Время заявки:</div>
            <div>" . date('d.m.Y H:i') . "</div>
        </div>
        
        <div class=\"footer\">
            <p>Это автоматическое уведомление с сайта {$from_name}</p>
            <p><a href=\"mailto:{$email}\">✉️ Ответить пользователю</a></p>
        </div>
    </body>
    </html>
    ";
    
    $mail->Body = $admin_email_body;
    
    // Текстовая версия для админа (со всеми данными)
    $mail->AltBody = "НОВАЯ ЗАЯВКА НА PDF\n\n" .
                    "👤 Имя: {$name}\n" .
                    "📧 Email: {$email}\n" .
                    (!empty($phone) ? "📞 Телефон: {$phone}\n" : "") .
                    (!empty($message_text) ? "💬 Сообщение: {$message_text}\n" : "") .
                    "📎 Файл: {$pdf_filename}\n" .
                    "🕐 Время: " . date('d.m.Y H:i') . "\n\n" .
                    "Автоматическое уведомление с сайта";

    // Отправляем уведомление админу
    $admin_email_sent = $mail->send();

    // =========================================================================
    // 3. ФИНАЛЬНЫЙ ОТВЕТ
    // =========================================================================
    
    if ($user_email_sent && $admin_email_sent) {
        sendJsonResponse(
            true, 
            '✅ PDF файл успешно отправлен на ваш email!',
            [
                'user_email_sent' => true,
                'admin_notification_sent' => true,
                'to_user' => $email,
                'to_admin' => $admin_email,
                'timestamp' => date('Y-m-d H:i:s')
            ]
        );
    } elseif ($user_email_sent && !$admin_email_sent) {
        // PDF отправлен, но уведомление админу не ушло
        sendJsonResponse(
            true, 
            '✅ PDF файл отправлен на ваш email!',
            [
                'user_email_sent' => true,
                'admin_notification_sent' => false,
                'to_user' => $email
            ]
        );
    } else {
        sendJsonResponse(false, '❌ Произошла ошибка при отправке');
    }

} catch (Exception $e) {
    sendJsonResponse(false, '❌ Произошла ошибка: ' . $e->getMessage());
}
?>