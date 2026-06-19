USE books_api;

-- 1. 给 books 表增加所有者字段和外键约束
ALTER TABLE books 
ADD COLUMN created_by INT NULL AFTER genre, 
ADD CONSTRAINT fk_books_user FOREIGN KEY(created_by) REFERENCES users(id) ON DELETE SET NULL;

-- 2. 给现有的测试数据分配所有者 (假设 user 1 是 admin, user 2 是 member)
UPDATE books SET created_by = 1 WHERE id IN (1, 3); 
UPDATE books SET created_by = 2 WHERE id = 2;

-- 3. 创建安全审计日志表
CREATE TABLE IF NOT EXISTS audit_log (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    occurred_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    actor_id INT NULL,
    action VARCHAR(50) NOT NULL,
    target VARCHAR(80) NULL,
    ip_address VARCHAR(45) NULL,
    detail VARCHAR(500) NULL,
    INDEX idx_action(action),
    INDEX idx_actor (actor_id)
) ENGINE=InnoDB;