<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Cruce de Datos</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <div class="container login-container">
        <form action="index.php?route=auth/login" method="POST" class="form-card">
            <h2>Iniciar Sesión</h2>
            <p>Usuario: <strong>admin</strong> | Contraseña: <strong>password123</strong></p>
            
            <?php if (isset($error)): ?>
                <div class="error-message"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <div class="form-group">
                <label for="username">Usuario:</label>
                <input type="text" id="username" name="username" required>
            </div>
            <div class="form-group">
                <label for="password">Contraseña:</label>
                <input type="password" id="password" name="password" required>
            </div>
            <button type="submit" class="btn">Entrar</button>
        </form>
    </div>
</body>
</html>