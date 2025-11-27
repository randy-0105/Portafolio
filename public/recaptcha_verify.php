<?php
error_log("recaptcha_verify.php accessed.");
require_once '../app/php/config.php';

$recaptcha_error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    error_log("recaptcha_verify.php: POST request received.");
    if (isset($_POST['g-recaptcha-response'])) {
        error_log("recaptcha_verify.php: g-recaptcha-response received: " . $_POST['g-recaptcha-response']);
        $recaptcha_response = $_POST['g-recaptcha-response'];
        $secret_key = RECAPTCHA_SECRET_KEY;

        $url = 'https://www.google.com/recaptcha/api/siteverify';
        $data = [
            'secret' => $secret_key,
            'response' => $recaptcha_response
        ];

        $options = [
            'http' => [
                'header' => "Content-type: application/x-www-form-urlencoded\r\n",
                'method' => 'POST',
                'content' => http_build_query($data)
            ]
        ];

        $context = stream_context_create($options);
        $result = file_get_contents($url, false, $context);
        $json_result = json_decode($result, true);

        if ($json_result['success']) {
            error_log("reCAPTCHA verification successful.");
            // reCAPTCHA verificado exitosamente
            if (isset($_GET['flow']) && $_GET['flow'] === 'forgot_password') {
                echo "<script>
                        setTimeout(function() {
                            window.location.href = 'forgot_username.php';
                        }, 3000); // Redirige después de 3 segundos
                      </script>";
            } else {
                echo "<script>
                        setTimeout(function() {
                            window.location.href = 'panel-secreto-2025.php';
                        }, 3000); // Redirige después de 3 segundos
                      </script>";
            }
            exit();
        } else {
            error_log("reCAPTCHA verification failed. Errors: " . implode(', ', $json_result['error-codes'] ?? ['Unknown error']));
            $recaptcha_error = 'Error en la verificación de reCAPTCHA. Por favor, inténtalo de nuevo.';
        }
    } else {
        error_log("recaptcha_verify.php: No g-recaptcha-response in POST data.");
        $recaptcha_error = 'Por favor, completa la verificación de reCAPTCHA.';
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verificación reCAPTCHA</title>
    <script src="https://www.google.com/recaptcha/api.js?onload=onloadCallback&render=explicit" async defer></script>
    <style>
        body {
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            background-color: #f0f0f0;
            font-family: Arial, sans-serif;
        }
        .recaptcha-container {
            background-color: #fff;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            text-align: center;
        }
        h1 {
            color: #333;
            margin-bottom: 20px;
        }
        .error-message {
            color: red;
            margin-top: 10px;
        }
    </style>
</head>
<body>
    <div class="recaptcha-container">
        <h1>Por favor, verifica que no eres un robot</h1>
        <?php if (!empty($recaptcha_error)): ?>
            <p class="error-message"><?php echo $recaptcha_error; ?></p>
        <?php endif; ?>
        <form action="" method="POST">
            <div id="recaptcha-widget" class="g-recaptcha" data-sitekey="<?php echo RECAPTCHA_SITE_KEY; ?>" data-callback="onSubmit"></div>
            <br/>
            <input type="submit" value="Verificar">
        </form>
    </div>

    <script>
        var onloadCallback = function() {
            grecaptcha.render('recaptcha-widget', {
                'sitekey' : '<?php echo RECAPTCHA_SITE_KEY; ?>',
                'callback' : onSubmit
            });
        };

        function onSubmit(token) {
            document.querySelector('form').submit();
        }
    </script>
</body>
</html>