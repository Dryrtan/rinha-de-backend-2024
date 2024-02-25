drop table if exists clientes cascade;
drop table if exists saldos;
drop table if exists transacoes;
drop table if exists locks;
drop function if exists acquire_lock;
drop function if exists release_lock;

CREATE TABLE clientes (
    id SERIAL PRIMARY KEY,
    nome VARCHAR(100) NOT NULL,
    limite INTEGER  NOT NULL
);

CREATE TABLE saldos (
    id SERIAL PRIMARY KEY,
    cliente_id INTEGER NOT NULL,
    saldo INTEGER NOT NULL,
    CONSTRAINT fk_clientes_saldos_id
		FOREIGN KEY (cliente_id) REFERENCES clientes(id)
);

CREATE TABLE transacoes (
    id SERIAL PRIMARY KEY,
    cliente_id INTEGER NOT NULL,
    tipo VARCHAR(1) NOT NULL,
    valor INTEGER NOT NULL,
    descricao VARCHAR(10) NOT NULL,
    realizada_em TIMESTAMP NOT NULL DEFAULT NOW(),
    CONSTRAINT fk_clientes_transacoes_id
		FOREIGN KEY (cliente_id) REFERENCES clientes(id)
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

INSERT INTO clientes (nome, limite)
VALUES
('Berta Ralha', 1000 * 100),
('Zé Trovão', 800 * 100),
('Felisbino Pamonha', 10000 * 100),
('Dolores Encrenca', 100000 * 100),
('Hortênsia Risonha', 5000 * 100);

INSERT INTO saldos (cliente_id, saldo)
VALUES
(1, 0),
(2, 0),
(3, 0),
(4, 0),
(5, 0);