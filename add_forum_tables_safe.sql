-- Safe SQL to add only the NEW forum tables
-- This will NOT touch any existing tables

SET FOREIGN_KEY_CHECKS = 0;

-- Create post table
CREATE TABLE IF NOT EXISTS post (
    id INT AUTO_INCREMENT NOT NULL,
    type VARCHAR(50) NOT NULL,
    title VARCHAR(255) NOT NULL,
    topic VARCHAR(100) DEFAULT NULL,
    content LONGTEXT NOT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME DEFAULT NULL,
    PRIMARY KEY (id)
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB;

-- Create tag table
CREATE TABLE IF NOT EXISTS tag (
    id INT AUTO_INCREMENT NOT NULL,
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(120) NOT NULL,
    UNIQUE INDEX UNIQ_name (name),
    PRIMARY KEY (id)
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB;

-- Create post_tag junction table
CREATE TABLE IF NOT EXISTS post_tag (
    post_id INT NOT NULL,
    tag_id INT NOT NULL,
    INDEX IDX_post (post_id),
    INDEX IDX_tag (tag_id),
    PRIMARY KEY (post_id, tag_id)
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB;

-- Create reply table
CREATE TABLE IF NOT EXISTS reply (
    id INT AUTO_INCREMENT NOT NULL,
    post_id INT NOT NULL,
    content LONGTEXT NOT NULL,
    author_name VARCHAR(180) DEFAULT NULL,
    upvotes INT DEFAULT 0 NOT NULL,
    created_at DATETIME NOT NULL,
    INDEX IDX_post_id (post_id),
    PRIMARY KEY (id)
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB;

-- Create reaction table
CREATE TABLE IF NOT EXISTS reaction (
    id INT AUTO_INCREMENT NOT NULL,
    user_identifier VARCHAR(191) NOT NULL,
    type VARCHAR(20) NOT NULL,
    created_at DATETIME NOT NULL,
    post_id INT DEFAULT NULL,
    reply_id INT DEFAULT NULL,
    INDEX IDX_post_id (post_id),
    INDEX IDX_reply_id (reply_id),
    UNIQUE INDEX UNIQ_user_post (user_identifier, post_id),
    UNIQUE INDEX UNIQ_user_reply (user_identifier, reply_id),
    PRIMARY KEY (id)
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci ENGINE = InnoDB;

-- Add foreign keys (only if tables exist)
ALTER TABLE post_tag 
    ADD CONSTRAINT IF NOT EXISTS FK_post_tag_post FOREIGN KEY (post_id) REFERENCES post (id) ON DELETE CASCADE,
    ADD CONSTRAINT IF NOT EXISTS FK_post_tag_tag FOREIGN KEY (tag_id) REFERENCES tag (id) ON DELETE CASCADE;

ALTER TABLE reply 
    ADD CONSTRAINT IF NOT EXISTS FK_reply_post FOREIGN KEY (post_id) REFERENCES post (id) ON DELETE CASCADE;

ALTER TABLE reaction 
    ADD CONSTRAINT IF NOT EXISTS FK_reaction_post FOREIGN KEY (post_id) REFERENCES post (id) ON DELETE CASCADE,
    ADD CONSTRAINT IF NOT EXISTS FK_reaction_reply FOREIGN KEY (reply_id) REFERENCES reply (id) ON DELETE CASCADE;

SET FOREIGN_KEY_CHECKS = 1;

-- Add some default tags
INSERT INTO tag (name, slug) VALUES 
    ('javascript', 'javascript'),
    ('python', 'python'),
    ('php', 'php'),
    ('symfony', 'symfony'),
    ('react', 'react'),
    ('career', 'career'),
    ('devops', 'devops')
ON DUPLICATE KEY UPDATE slug = slug;
