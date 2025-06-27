CREATE DATABASE IF NOT EXISTS mail_service;
USE mail_service;

-- Tabla de plantillas de correo
CREATE TABLE mail_templates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) UNIQUE NOT NULL,
    subject VARCHAR(255) NOT NULL,
    body_html TEXT NOT NULL,
    body_text TEXT,
    variables JSON,
    template_type ENUM('verification', 'password_reset', 'login_alert', 'custom') NOT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_name (name),
    INDEX idx_type (template_type),
    INDEX idx_active (is_active)
);

-- Tabla de logs de correos enviados
CREATE TABLE mail_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    template_id INT,
    recipient_email VARCHAR(255) NOT NULL,
    recipient_name VARCHAR(255),
    subject VARCHAR(255) NOT NULL,
    status ENUM('pending', 'sent', 'failed', 'bounced') DEFAULT 'pending',
    error_message TEXT,
    variables_used JSON,
    sent_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    -- Datos adicionales para debugging
    smtp_response TEXT,
    retry_count INT DEFAULT 0,
    priority ENUM('low', 'normal', 'high') DEFAULT 'normal',
    
    FOREIGN KEY (template_id) REFERENCES mail_templates(id) ON DELETE SET NULL,
    INDEX idx_recipient (recipient_email),
    INDEX idx_status (status),
    INDEX idx_created (created_at),
    INDEX idx_template (template_id)
);

-- Tabla de cola de correos
CREATE TABLE mail_queue (
    id INT AUTO_INCREMENT PRIMARY KEY,
    template_id INT NOT NULL,
    recipient_email VARCHAR(255) NOT NULL,
    recipient_name VARCHAR(255),
    variables JSON,
    priority ENUM('low', 'normal', 'high') DEFAULT 'normal',
    status ENUM('pending', 'processing', 'completed', 'failed') DEFAULT 'pending',
    scheduled_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    attempts INT DEFAULT 0,
    max_attempts INT DEFAULT 3,
    last_attempt_at TIMESTAMP NULL,
    error_message TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (template_id) REFERENCES mail_templates(id) ON DELETE CASCADE,
    INDEX idx_status (status),
    INDEX idx_priority (priority),
    INDEX idx_scheduled (scheduled_at),
    INDEX idx_template (template_id)
);

-- Tabla de archivos adjuntos
CREATE TABLE mail_attachments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    mail_log_id INT,
    filename VARCHAR(255) NOT NULL,
    original_filename VARCHAR(255) NOT NULL,
    file_path VARCHAR(500) NOT NULL,
    file_size INT NOT NULL,
    mime_type VARCHAR(100) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (mail_log_id) REFERENCES mail_logs(id) ON DELETE CASCADE,
    INDEX idx_mail_log (mail_log_id)
);

-- Tabla de configuración SMTP
CREATE TABLE smtp_configs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) UNIQUE NOT NULL,
    host VARCHAR(255) NOT NULL,
    port INT NOT NULL DEFAULT 587,
    username VARCHAR(255) NOT NULL,
    password VARCHAR(255) NOT NULL,
    encryption ENUM('none', 'tls', 'ssl') DEFAULT 'tls',
    is_active BOOLEAN DEFAULT TRUE,
    is_default BOOLEAN DEFAULT FALSE,
    max_daily_sends INT DEFAULT 1000,
    current_daily_sends INT DEFAULT 0,
    last_reset_date DATE DEFAULT (CURRENT_DATE),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_active (is_active),
    INDEX idx_default (is_default)
);

-- Tabla de métricas diarias
CREATE TABLE daily_metrics (
    id INT AUTO_INCREMENT PRIMARY KEY,
    date DATE NOT NULL,
    emails_sent INT DEFAULT 0,
    emails_failed INT DEFAULT 0,
    templates_used JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    UNIQUE KEY unique_date (date),
    INDEX idx_date (date)
);

-- Insertar plantillas por defecto
INSERT INTO mail_templates (name, subject, body_html, body_text, template_type, variables) VALUES
('user_verification', 
 'Verifica tu cuenta - {{site_name}}',
 '<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Verificación de Cuenta</title>
</head>
<body style="font-family: Arial, sans-serif; margin: 0; padding: 20px; background-color: #f4f4f4;">
    <div style="max-width: 600px; margin: 0 auto; background-color: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1);">
        <h1 style="color: #333; text-align: center;">¡Bienvenido {{name}}!</h1>
        <p style="color: #666; font-size: 16px; line-height: 1.6;">
            Gracias por registrarte en nuestro servicio. Para completar tu registro, por favor verifica tu dirección de correo electrónico haciendo clic en el siguiente botón:
        </p>
        <div style="text-align: center; margin: 30px 0;">
            <a href="{{verification_url}}" style="background-color: #007bff; color: white; padding: 12px 30px; text-decoration: none; border-radius: 5px; font-weight: bold;">Verificar mi cuenta</a>
        </div>
        <p style="color: #666; font-size: 14px;">
            Si no puedes hacer clic en el botón, copia y pega el siguiente enlace en tu navegador:<br>
            <a href="{{verification_url}}">{{verification_url}}</a>
        </p>
        <p style="color: #999; font-size: 12px; margin-top: 30px;">
            Si no creaste esta cuenta, puedes ignorar este correo.
        </p>
    </div>
</body>
</html>',
 'Hola {{name}},\n\nGracias por registrarte. Para verificar tu cuenta, visita: {{verification_url}}\n\nSi no creaste esta cuenta, ignora este mensaje.',
 'verification',
 '["name", "verification_url", "site_name"]'),

('password_reset',
 'Restablecer contraseña - {{site_name}}',
 '<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Restablecer Contraseña</title>
</head>
<body style="font-family: Arial, sans-serif; margin: 0; padding: 20px; background-color: #f4f4f4;">
    <div style="max-width: 600px; margin: 0 auto; background-color: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1);">
        <h1 style="color: #333; text-align: center;">Restablecer Contraseña</h1>
        <p style="color: #666; font-size: 16px; line-height: 1.6;">
            Hola {{name}}, recibimos una solicitud para restablecer la contraseña de tu cuenta.
        </p>
        <div style="text-align: center; margin: 30px 0;">
            <a href="{{reset_url}}" style="background-color: #dc3545; color: white; padding: 12px 30px; text-decoration: none; border-radius: 5px; font-weight: bold;">Restablecer Contraseña</a>
        </div>
        <p style="color: #666; font-size: 14px;">
            Este enlace expirará en 1 hora por seguridad.
        </p>
        <p style="color: #999; font-size: 12px; margin-top: 30px;">
            Si no solicitaste este cambio, ignora este correo. Tu contraseña no será modificada.
        </p>
    </div>
</body>
</html>',
 'Hola {{name}},\n\nPara restablecer tu contraseña, visita: {{reset_url}}\n\nEste enlace expira en 1 hora.\n\nSi no solicitaste esto, ignora este mensaje.',
 'password_reset',
 '["name", "reset_url", "site_name"]'),

('login_alert',
 'Nuevo inicio de sesión detectado - {{site_name}}',
 '<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Alerta de Inicio de Sesión</title>
</head>
<body style="font-family: Arial, sans-serif; margin: 0; padding: 20px; background-color: #f4f4f4;">
    <div style="max-width: 600px; margin: 0 auto; background-color: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1);">
        <h1 style="color: #333; text-align: center;">Inicio de Sesión Detectado</h1>
        <p style="color: #666; font-size: 16px; line-height: 1.6;">
            Hola {{name}}, se ha detectado un nuevo inicio de sesión en tu cuenta:
        </p>
        <div style="background-color: #f8f9fa; padding: 15px; border-radius: 5px; margin: 20px 0;">
            <p><strong>Fecha:</strong> {{login_date}}</p>
            <p><strong>IP:</strong> {{ip_address}}</p>
            <p><strong>Dispositivo:</strong> {{user_agent}}</p>
        </div>
        <p style="color: #666; font-size: 14px;">
            Si fuiste tú, puedes ignorar este mensaje. Si no reconoces esta actividad, te recomendamos cambiar tu contraseña inmediatamente.
        </p>
    </div>
</body>
</html>',
 'Hola {{name}},\n\nNuevo inicio de sesión detectado:\nFecha: {{login_date}}\nIP: {{ip_address}}\n\nSi no fuiste tú, cambia tu contraseña inmediatamente.',
 'login_alert',
 '["name", "login_date", "ip_address", "user_agent", "site_name"]');

-- Insertar configuración SMTP por defecto
INSERT INTO smtp_configs (name, host, port, username, password, encryption, is_default) VALUES
('default_smtp', 'smtp.gmail.com', 587, 'your-email@gmail.com', 'your-app-password', 'tls', TRUE);
