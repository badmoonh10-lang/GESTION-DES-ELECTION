-- Base: TestElecteur
-- Charset conseillé: utf8mb4

SET NAMES utf8mb4;
SET time_zone = "+00:00";

CREATE TABLE settings (
  id INT PRIMARY KEY AUTO_INCREMENT,
  key_name VARCHAR(64) NOT NULL UNIQUE,
  key_value VARCHAR(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO settings(key_name, key_value) VALUES
('list_closed', '0'),
('card_signature_path', 'assets/img/signature.png'),
('voting_open', '0');

CREATE TABLE users (
  id INT PRIMARY KEY AUTO_INCREMENT,
  username VARCHAR(80) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  role ENUM('DIRECTION','AGENT','ELECTOR') NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE electors (
  id INT PRIMARY KEY AUTO_INCREMENT,
  user_id INT NULL,
  nom VARCHAR(100) NOT NULL,
  prenom VARCHAR(100) NOT NULL,
  genre ENUM('M','F','AUTRE') NOT NULL,
  age INT NOT NULL,
  cni VARCHAR(50) NOT NULL UNIQUE,
  profile_photo VARCHAR(255) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT NULL,
  CONSTRAINT fk_electors_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE enrollments (
  id INT PRIMARY KEY AUTO_INCREMENT,
  dossier_code VARCHAR(20) NOT NULL UNIQUE,
  elector_id INT NOT NULL,
  created_by_role ENUM('ONLINE','AGENT') NOT NULL,
  created_by_user_id INT NULL,
  status ENUM('PRE_ENROLLED','SUBMITTED','APPROVED','REJECTED') NOT NULL DEFAULT 'PRE_ENROLLED',
  direction_comment VARCHAR(255) NULL,
  submitted_at TIMESTAMP NULL DEFAULT NULL,
  reviewed_at TIMESTAMP NULL DEFAULT NULL,
  approved_at TIMESTAMP NULL DEFAULT NULL,
  rejected_at TIMESTAMP NULL DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_enrollments_elector FOREIGN KEY (elector_id) REFERENCES electors(id) ON DELETE CASCADE,
  CONSTRAINT fk_enrollments_user FOREIGN KEY (created_by_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE attachments (
  id INT PRIMARY KEY AUTO_INCREMENT,
  enrollment_id INT NOT NULL,
  file_type ENUM('CNI','ACTE_NAISSANCE','AUTRE') NOT NULL,
  file_path VARCHAR(255) NOT NULL,
  uploaded_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_attachments_enrollment FOREIGN KEY (enrollment_id) REFERENCES enrollments(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE cards (
  id INT PRIMARY KEY AUTO_INCREMENT,
  enrollment_id INT NOT NULL UNIQUE,
  qr_code VARCHAR(255) NOT NULL,
  lieu VARCHAR(120) NOT NULL,
  signature_path VARCHAR(255) NOT NULL,
  generated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_cards_enrollment FOREIGN KEY (enrollment_id) REFERENCES enrollments(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Candidats (liés à un électeur déjà dans la liste électorale)
CREATE TABLE candidates (
  id INT PRIMARY KEY AUTO_INCREMENT,
  elector_id INT NOT NULL,
  party_name VARCHAR(120) NOT NULL,
  program_text TEXT NULL,
  caution_file VARCHAR(255) NOT NULL,
  approved TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_candidates_elector FOREIGN KEY (elector_id) REFERENCES electors(id) ON DELETE CASCADE,
  UNIQUE KEY uniq_candidate_elector (elector_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Agents de terrain (profil + lien compte AGENT)
CREATE TABLE field_agents (
  id INT PRIMARY KEY AUTO_INCREMENT,
  user_id INT NULL UNIQUE,
  matricule VARCHAR(20) NOT NULL UNIQUE,
  nom VARCHAR(100) NOT NULL,
  prenom VARCHAR(100) NOT NULL,
  genre ENUM('M','F','AUTRE') NOT NULL,
  age INT NOT NULL,
  cni VARCHAR(50) NOT NULL UNIQUE,
  profile_photo VARCHAR(255) NULL,
  bureau_vote VARCHAR(120) NULL DEFAULT 'Bureau principal',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT NULL,
  CONSTRAINT fk_field_agents_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE agent_cards (
  id INT PRIMARY KEY AUTO_INCREMENT,
  field_agent_id INT NOT NULL UNIQUE,
  qr_code VARCHAR(255) NOT NULL,
  lieu VARCHAR(120) NOT NULL,
  signature_path VARCHAR(255) NOT NULL,
  generated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_agent_cards_agent FOREIGN KEY (field_agent_id) REFERENCES field_agents(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Votes (1 vote par électeur pour un candidat)
CREATE TABLE votes (
  id INT PRIMARY KEY AUTO_INCREMENT,
  elector_id INT NOT NULL,
  candidate_id INT NOT NULL,
  vote_type ENUM('ONLINE','PHYSICAL') NOT NULL DEFAULT 'ONLINE',
  recorded_by_user_id INT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_votes_elector FOREIGN KEY (elector_id) REFERENCES electors(id) ON DELETE CASCADE,
  CONSTRAINT fk_votes_candidate FOREIGN KEY (candidate_id) REFERENCES candidates(id) ON DELETE CASCADE,
  CONSTRAINT fk_votes_recorded_by FOREIGN KEY (recorded_by_user_id) REFERENCES users(id) ON DELETE SET NULL,
  UNIQUE KEY uniq_vote_elector (elector_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Comptes demo
-- direction123 / agent123 / electeur123
INSERT INTO users(username, password_hash, role) VALUES
('direction', '$2y$10$xP1ENa7h6VV/oKPcAs/ifejVVslw7LEcKaBezFs1LfZukIzZefH/y', 'DIRECTION'),
('agent',     '$2y$10$4B.pA6DMXW7Fa9Umgdy58.KN2TKVXmisLvkR.XFKzP.ve/K0J3ssu', 'AGENT'),
('electeur',  '$2y$10$kGQdiXdG0N0xQsyoNoViN.f.YjZMyGkGt5MtaptPNJbYim/z0n5.m', 'ELECTOR');

-- Électeur demo lié au user electeur
INSERT INTO electors(user_id, nom, prenom, genre, age, cni, profile_photo)
SELECT id, 'DOE', 'John', 'M', 30, 'CNI-DEMO-001', NULL FROM users WHERE username='electeur';

-- Dossier demo
INSERT INTO enrollments(dossier_code, elector_id, created_by_role, created_by_user_id, status, submitted_at)
SELECT 'DOS-000001', e.id, 'ONLINE', u.id, 'SUBMITTED', NOW()
FROM electors e
JOIN users u ON u.id = e.user_id
WHERE u.username='electeur';

-- Agent de terrain demo
INSERT INTO field_agents(user_id, matricule, nom, prenom, genre, age, cni, bureau_vote)
SELECT u.id, 'AGT-000001', 'KAMGA', 'Paul', 'M', 35, 'CNI-AGENT-001', 'Bureau IUT-FV Bandjoun'
FROM users u WHERE u.username='agent';

INSERT INTO agent_cards(field_agent_id, qr_code, lieu, signature_path)
SELECT fa.id, fa.matricule, 'IUT-Fv Bandjoun', 'assets/img/signature.png'
FROM field_agents fa WHERE fa.matricule='AGT-000001';


