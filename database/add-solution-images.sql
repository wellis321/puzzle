-- Add image support to solutions table
-- Run this to enable AI-generated solution images

ALTER TABLE solutions 
ADD COLUMN image_path VARCHAR(255) NULL AFTER detailed_reasoning,
ADD COLUMN image_prompt TEXT NULL AFTER image_path;

-- Add index for image lookups
CREATE INDEX idx_solution_image ON solutions (image_path);

