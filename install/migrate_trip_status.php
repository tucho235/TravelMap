<?php
/**
 * Migration: Update trip status from 'public' to 'published' and remove 'planned'
 * 
 * Run this ONCE to update your database
 */

require_once __DIR__ . '/../config/db.php';

try {
    $db = getDB();
    
    echo "Starting trip status migration...\n";
    
    // Step 0: Drop temp column if it exists from a previous failed run
    echo "Step 0: Cleaning up any previous migration attempts...\n";
    try {
        $db->exec("ALTER TABLE trips DROP COLUMN temp_status");
        echo "Removed existing temp_status column\n";
    } catch (PDOException $e) {
        echo "No temp column to clean up (OK)\n";
    }
    
    // Step 1: Add a temporary column to store the mapping
    echo "Step 1: Adding temporary column...\n";
    $db->exec("ALTER TABLE trips ADD COLUMN temp_status VARCHAR(20)");
    
    // Step 2: Map old statuses to new ones
    echo "Step 2: Mapping statuses (public/planned -> published, draft -> draft)...\n";
    $db->exec("UPDATE trips SET temp_status = 'published' WHERE status IN ('public', 'planned')");
    $db->exec("UPDATE trips SET temp_status = 'draft' WHERE status = 'draft'");
    
    // Step 3: Change status column to VARCHAR temporarily to avoid truncation
    echo "Step 3: Converting status column to VARCHAR...\n";
    $db->exec("ALTER TABLE trips MODIFY status VARCHAR(20)");
    
    // Step 4: Copy temp values to status
    echo "Step 4: Applying new statuses...\n";
    $db->exec("UPDATE trips SET status = temp_status");
    
    // Step 5: Now change to the new ENUM
    echo "Step 5: Updating enum to new values...\n";
    $db->exec("ALTER TABLE trips MODIFY status ENUM('draft', 'published') DEFAULT 'draft'");
    
    // Step 6: Remove temporary column
    echo "Step 6: Cleaning up...\n";
    $db->exec("ALTER TABLE trips DROP COLUMN temp_status");
    
    // Show results
    $published = $db->query("SELECT COUNT(*) FROM trips WHERE status = 'published'")->fetchColumn();
    $draft = $db->query("SELECT COUNT(*) FROM trips WHERE status = 'draft'")->fetchColumn();
    
    echo "\n✅ Migration completed successfully!\n";
    echo "Results:\n";
    echo "  - Published: {$published} trips\n";
    echo "  - Draft: {$draft} trips\n";
    
} catch (PDOException $e) {
    echo "❌ Error during migration: " . $e->getMessage() . "\n";
    exit(1);
}
