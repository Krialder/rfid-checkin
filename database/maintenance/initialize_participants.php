<?php
/**
 * Maintenance Script: Initialize current_participants for existing events
 * 
 * This script recalculates and updates the current_participants count
 * for all events based on the CheckIn table data.
 * 
 * Run this script after adding the current_participants column to sync data.
 */

require_once '../config.php';
require_once '../database.php';

try {
    $db = getDB();
    
    echo "Starting participant count initialization...\n";
    
    // Call the stored procedure to recalculate all participant counts
    $stmt = $db->prepare("CALL sp_recalculate_participants()");
    $stmt->execute();
    
    // Get summary of updated events
    $stmt = $db->prepare("
        SELECT 
            COUNT(*) as total_events,
            SUM(current_participants) as total_participants,
            AVG(current_participants) as avg_participants_per_event
        FROM Events 
        WHERE active = 1
    ");
    $stmt->execute();
    $summary = $stmt->fetch();
    
    echo "✅ Participant counts initialized successfully!\n";
    echo "📊 Summary:\n";
    echo "   - Total active events: " . $summary['total_events'] . "\n";
    echo "   - Total participants: " . $summary['total_participants'] . "\n";
    echo "   - Average participants per event: " . round($summary['avg_participants_per_event'], 2) . "\n";
    
    // Show events with participants
    echo "\n🎯 Events with current participants:\n";
    $stmt = $db->prepare("
        SELECT name, current_participants, capacity
        FROM Events 
        WHERE current_participants > 0 
        ORDER BY current_participants DESC
        LIMIT 10
    ");
    $stmt->execute();
    $events = $stmt->fetchAll();
    
    if (empty($events)) {
        echo "   No events currently have participants.\n";
    } else {
        foreach ($events as $event) {
            $capacity_info = $event['capacity'] ? " / {$event['capacity']}" : "";
            echo "   - {$event['name']}: {$event['current_participants']}{$capacity_info} participants\n";
        }
    }
    
    echo "\n✨ Done! The current_participants column is now properly synchronized.\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}
