<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require __DIR__ . '/libs/PHPMailer/Exception.php';
require __DIR__ . '/libs/PHPMailer/PHPMailer.php';
require __DIR__ . '/libs/PHPMailer/SMTP.php';

function enviarCorreo(string $destino, string $nombre, string $asunto, string $html): bool
{
    // Sin datos de correo configurados (.env) no se intenta enviar: evita
    // que la página quede esperando la conexión con el servidor SMTP.
    if (!getenv('SMTP_USER') || !getenv('SMTP_PASS')) {
        error_log('mailer: falta SMTP_USER o SMTP_PASS en el .env; no se envió el correo.');
        return false;
    }

    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = getenv('SMTP_USER');
        $mail->Password   = getenv('SMTP_PASS');
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;
        $mail->Timeout    = 15;
        $mail->CharSet    = 'UTF-8';

        $mail->setFrom(getenv('SMTP_USER'), 'Barbería Netro');
        $mail->addAddress($destino, $nombre);
        $mail->isHTML(true);
        $mail->Subject = $asunto;
        $mail->Body    = $html;
        $mail->AltBody = strip_tags($html);

        return $mail->send();
    } catch (Exception $e) {
        error_log('PHPMailer: ' . $mail->ErrorInfo);
        return false;
    }
}
?>