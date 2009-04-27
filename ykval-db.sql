-- DROP DATABASE ykval;
CREATE DATABASE ykval;
USE ykval;

CREATE TABLE clients (
  id INT NOT NULL AUTO_INCREMENT,
  active BOOLEAN DEFAULT TRUE,
  created DATETIME NOT NULL,
  email VARCHAR(255) NOT NULL DEFAULT '',
  secret VARCHAR(60) NOT NULL DEFAULT '',
  chk_time BOOLEAN DEFAULT TRUE,
  PRIMARY KEY (id)
);

CREATE TABLE yubikeys (
  id INT NOT NULL UNIQUE AUTO_INCREMENT,
  active BOOLEAN DEFAULT TRUE,
  created DATETIME NOT NULL,
  accessed DATETIME,
  publicName VARCHAR(16) UNIQUE NOT NULL,
  internalName VARCHAR(12) NOT NULL,
  counter INT,
  low INT,
  high INT,
  sessionUse INT,
  PRIMARY KEY (id)
);

-- DROP USER ykval_verifier;
CREATE USER ykval_verifier;
GRANT SELECT,INSERT,UPDATE(accessed, counter, low, high, sessionUse)
       ON ykval.yubikeys to 'ykval_verifier'@'localhost';
GRANT SELECT(id, secret, chk_time, active)
       ON ykval.clients to 'ykval_verifier'@'localhost';
FLUSH PRIVILEGES;
