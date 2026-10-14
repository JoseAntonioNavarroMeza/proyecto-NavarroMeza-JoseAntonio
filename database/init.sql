CREATE TABLE IF NOT EXISTS equipos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    numero_serie VARCHAR(50) NOT NULL UNIQUE,
    laboratorio VARCHAR(50) NOT NULL,
    estado ENUM('Operativo', 'En Mantenimiento', 'Baja') DEFAULT 'Operativo',
    creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

INSERT INTO equipos (nombre, numero_serie, laboratorio, estado) VALUES
('Osciloscopio Digital Tektronix', 'OSC-2024-001', 'Electrónica', 'Operativo'),
('Microscopio Óptico Binocular', 'MIC-2023-042', 'Química', 'Operativo'),
('Generador de Funciones Rigol', 'GEN-2025-110', 'Redes y Telecomunicaciones', 'En Mantenimiento');