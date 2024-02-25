-- Criar tabela clientes no postgres
CREATE TABLE clientes (
    id SERIAL PRIMARY KEY,
    nome VARCHAR(100) NOT NULL,
    limite DECIMAL(10,2) NOT NULL
);

-- População de dados
INSERT INTO clientes (nome, limite)
VALUES
('Berta Ralha', 1000 * 100),
('Zé Trovão', 800 * 100),
('Felisbino Pamonha', 10000 * 100),
('Dolores Encrenca', 100000 * 100),
('Hortênsia Risonha', 5000 * 100);