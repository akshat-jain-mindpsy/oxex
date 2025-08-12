-- Add subfield_id column to pass_standards table
-- This allows specifying which subfield of a selected field to check

ALTER TABLE pass_standards 
ADD COLUMN subfield_id INT NULL AFTER field_value,
ADD INDEX idx_subfield_id (subfield_id);

-- Add foreign key constraint if the select_types table exists
-- ALTER TABLE pass_standards 
-- ADD CONSTRAINT fk_pass_standards_subfield 
-- FOREIGN KEY (subfield_id) REFERENCES select_types(stid) ON DELETE SET NULL;

-- Note: Uncomment the foreign key constraint above if you want referential integrity
-- This will ensure that subfield_id references a valid field in select_types
