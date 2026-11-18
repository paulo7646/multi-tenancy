-- Rode no PostgreSQL, como superusuário, UMA vez, conectado ao banco da base pai (o mesmo
-- do auth_dbname do pgbouncer.ini). Troque as senhas e o nome do usuário do app.

-- 1) Usuário que o PgBouncer usa só para consultar as senhas (auth_query).
CREATE ROLE pgbouncer LOGIN PASSWORD 'troque-esta-senha';
-- Restrinja o login dele no pg_hba.conf (só 127.0.0.1, só o banco da base pai).

-- 2) Função que devolve o hash de senha de um usuário. Fica num schema só dela, roda com os
-- privilégios de quem a criou (SECURITY DEFINER) e só devolve usuários que NÃO são
-- superusuários (o usuário do app não deve ser superusuário em produção).
CREATE SCHEMA IF NOT EXISTS pgbouncer;
REVOKE ALL ON SCHEMA pgbouncer FROM PUBLIC;
GRANT USAGE ON SCHEMA pgbouncer TO pgbouncer;

CREATE OR REPLACE FUNCTION pgbouncer.usuario(p_usuario text)
RETURNS TABLE (usename name, passwd text)
LANGUAGE sql
SECURITY DEFINER
SET search_path = pg_catalog, pg_temp
AS $$
    SELECT s.usename, s.passwd FROM pg_catalog.pg_shadow s WHERE s.usename = p_usuario AND NOT s.usesuper
$$;
REVOKE ALL ON FUNCTION pgbouncer.usuario(text) FROM PUBLIC;
GRANT EXECUTE ON FUNCTION pgbouncer.usuario(text) TO pgbouncer;

-- 3) Segurança contra sessão esquecida dentro de transação: só para o usuário do app (não
-- vale para migrations longas feitas por outro usuário nem para o psql de manutenção).
-- ALTER ROLE nome_do_usuario_do_app SET idle_in_transaction_session_timeout = '30s';

-- 4) Confira o limite de conexões e deixe folga para o PgBouncer, as migrations e o painel:
--   SHOW max_connections;
