-- Migration v2 : agents de terrain, votes physiques
-- Exécuter sur la base TestElecteur existante

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS field_agents (
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

CREATE TABLE IF NOT EXISTS agent_cards (
  id INT PRIMARY KEY AUTO_INCREMENT,
  field_agent_id INT NOT NULL UNIQUE,
  qr_code VARCHAR(255) NOT NULL,
  lieu VARCHAR(120) NOT NULL,
  signature_path VARCHAR(255) NOT NULL,
  generated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_agent_cards_agent FOREIGN KEY (field_agent_id) REFERENCES field_agents(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Colonnes votes (ignorer si déjà présentes)
ALTER TABLE votes ADD COLUMN vote_type ENUM('ONLINE','PHYSICAL') NOT NULL DEFAULT 'ONLINE';
ALTER TABLE votes ADD COLUMN recorded_by_user_id INT NULL;
ALTER TABLE votes ADD CONSTRAINT fk_votes_recorded_by FOREIGN KEY (recorded_by_user_id) REFERENCES users(id) ON DELETE SET NULL;

INSERT IGNORE INTO field_agents(user_id, matricule, nom, prenom, genre, age, cni, bureau_vote)
SELECT u.id, 'AGT-000001', 'KAMGA', 'Paul', 'M', 35, 'CNI-AGENT-001', 'Bureau IUT-FV Bandjoun'
FROM users u WHERE u.username = 'agent' AND u.role = 'AGENT'
LIMIT 1;

INSERT IGNORE INTO agent_cards(field_agent_id, qr_code, lieu, signature_path)
SELECT fa.id, fa.matricule, 'IUT-Fv Bandjoun', 'assets/img/signature.png'
FROM field_agents fa WHERE fa.matricule = 'AGT-000001'
LIMIT 1;
