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
  active BOOLEAN DEFAULT TRUE,
  created INT NOT NULL,
  modified INT NOT NULL,
  yk_publicname VARCHAR(16) UNIQUE NOT NULL,
  yk_counter INT NOT NULL,
  yk_use INT NOT NULL,
  yk_low INT NOT NULL,
  yk_high INT NOT NULL,
  nonce VARCHAR(40) DEFAULT '',
  notes VARCHAR(100) DEFAULT '',
  PRIMARY KEY (yk_publicname)
);

CREATE TABLE queue (
  queued INT DEFAULT NULL,
  modified INT DEFAULT NULL,
  server_nonce VARCHAR(32) NOT NULL,
  otp VARCHAR(100) NOT NULL,
  server VARCHAR(100) NOT NULL,
  info VARCHAR(256) NOT NULL
);
