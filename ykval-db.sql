CREATE TABLE clients (
  id INT NOT NULL UNIQUE,
  active BOOLEAN DEFAULT TRUE,
  created INT NOT NULL,
  secret VARCHAR(60) NOT NULL DEFAULT '',
  email VARCHAR(255),
  notes VARCHAR(100) DEFAULT '',
  otp VARCHAR(100) DEFAULT '',
  PRIMARY KEY (id)
);

CREATE TABLE yubikeys (
  id INT NOT NULL UNIQUE AUTO_INCREMENT,
  active BOOLEAN DEFAULT TRUE,
  created INT NOT NULL,
  modified INT NOT NULL,
  yk_publicname VARCHAR(16) UNIQUE NOT NULL,
  yk_internalname VARCHAR(12) NOT NULL,
  yk_counter INT NOT NULL,
  yk_use INT NOT NULL,
  yk_low INT,
  yk_high INT,
  nonce VARCHAR(32) DEFAULT '',
  notes VARCHAR(100) DEFAULT '',
  PRIMARY KEY (id)
);

CREATE TABLE queue (
  id INT NOT NULL UNIQUE AUTO_INCREMENT,
  queued INT DEFAULT NULL,
  modified INT DEFAULT NULL,
  random_key INT,
  otp VARCHAR(100) NOT NULL,
  server VARCHAR(100) NOT NULL,
  info VARCHAR(256) NOT NULL,
  PRIMARY KEY (id)
);
