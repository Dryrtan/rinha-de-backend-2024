drop table if exists clientes cascade;
drop table if exists saldos;
drop table if exists transacoes;

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

CREATE FUNCTION executar_transacao(valor integer, id_usuario integer, descricao text, tipo_transacao text)
RETURNS VARCHAR
AS $$
DECLARE
    cliente_limite integer;
    cliente_saldo integer;
    cliente_saldo_final integer;
BEGIN
    LOCK TABLE saldos IN EXCLUSIVE MODE;
	cliente_limite := (SELECT limite FROM clientes WHERE id = id_usuario);
    cliente_saldo := (SELECT saldo FROM saldos WHERE cliente_id = id_usuario);
    cliente_saldo_final := 0;
    
    IF (tipo_transacao = 'c') THEN
        cliente_saldo_final := cliente_saldo + valor;
        UPDATE saldos SET saldo = cliente_saldo_final WHERE cliente_id = id_usuario;        
    ELSE
        cliente_saldo_final := cliente_saldo - valor;
        IF (cliente_saldo_final < (-1 * cliente_limite)) THEN
            RETURN 'Limite ultrapassado';
        END IF;
        UPDATE saldos SET saldo = cliente_saldo_final WHERE cliente_id = id_usuario;
    END IF;

    INSERT INTO transacoes (cliente_id, tipo, valor, descricao) 
    VALUES (id_usuario, tipo_transacao, valor, descricao);

    RETURN 'Transação concluída';

    EXCEPTION
        WHEN OTHERS THEN
            RETURN 'Erro na transação';
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