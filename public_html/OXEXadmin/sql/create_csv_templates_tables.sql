-- CSV Templates Main Table
CREATE TABLE IF NOT EXISTS csv_templates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    template_name VARCHAR(255) NOT NULL,
    description TEXT,
    created_by VARCHAR(100),
    date_created TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- CSV Template Columns Table
CREATE TABLE IF NOT EXISTS csv_template_columns (
    id INT AUTO_INCREMENT PRIMARY KEY,
    template_id INT,
    tbid TINYINT UNSIGNED COMMENT 'Table ID from tabs_tbl',
    stid MEDIUMINT UNSIGNED COMMENT 'Field ID from select_types',
    column_order INT DEFAULT 0,
    FOREIGN KEY (template_id) REFERENCES csv_templates(id) ON DELETE CASCADE,
    FOREIGN KEY (tbid) REFERENCES tabs_tbl(tbid) ON DELETE CASCADE,
    FOREIGN KEY (stid) REFERENCES select_types(stid) ON DELETE CASCADE
);


