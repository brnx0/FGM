#!/bin/bash
# Cria o usuario de testes com a mesma senha do usuario de aplicacao (APP_USER_PASSWORD).
set -e
sqlplus -s / as sysdba <<SQL
ALTER SESSION SET CONTAINER = FREEPDB1;
CREATE USER mapacultura_test IDENTIFIED BY "${APP_USER_PASSWORD}" QUOTA UNLIMITED ON USERS;
GRANT CREATE SESSION, CREATE TABLE, CREATE SEQUENCE, CREATE TRIGGER, CREATE VIEW TO mapacultura_test;
EXIT;
SQL
