-- Criar tabela clientes no postgres
CREATE TABLE clientes (
    id SERIAL PRIMARY KEY,
    nome VARCHAR(100) NOT NULL,
    limite INTEGER  NOT NULL
);

CREATE TABLE saldos (
    id SERIAL PRIMARY KEY,
    cliente_id INTEGER NOT NULL REFERENCES clientes(id),
    saldo INTEGER NOT NULL
    CONSTRAINT fk_clientes FOREIGN KEY(cliente_id) REFERENCES clientes(id)
);

CREATE TABLE locks (
    id SERIAL PRIMARY KEY,
    cliente_id INTEGER
);

CREATE FUNCTION acquire_lock(cliente INTEGER)
RETURNS BOOLEAN
AS $$
BEGIN
    IF EXISTS (SELECT cliente_id FROM locks WHERE cliente_id = cliente) THEN
        RETURN false;
    END IF;

    INSERT INTO locks (cliente_id) VALUES ( cliente);

    RETURN true;
END
$$ LANGUAGE plpgsql;

CREATE FUNCTION release_lock(cliente INTEGER)
RETURNS BOOLEAN
AS $$
BEGIN
    DELETE FROM locks WHERE cliente_id = cliente;
    RETURN true;
END
$$ LANGUAGE plpgsql;

-- População de dados
INSERT INTO clientes (nome, limite)
VALUES
('Berta Ralha', 1000 * 100),
('Zé Trovão', 800 * 100),
('Felisbino Pamonha', 10000 * 100),
('Dolores Encrenca', 100000 * 100),
('Hortênsia Risonha', 5000 * 100);