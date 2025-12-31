-- Migration: Add map style setting
-- This allows users to choose different basemap styles for the public map

INSERT INTO settings (setting_key, setting_value, setting_type, description) 
VALUES ('map_style', 'voyager', 'string', 'Map basemap style (positron, voyager, dark-matter, osm-liberty)')
ON DUPLICATE KEY UPDATE setting_value = setting_value;
