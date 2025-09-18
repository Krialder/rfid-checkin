<?php
/**
 * Repository Pattern Unit Tests
 * 
 * Comprehensive testing of the repository pattern implementation
 * including BaseRepository functionality and specific repositories.
 */

require_once __DIR__ . '/../../core/config.php';
require_once __DIR__ . '/../../core/database.php';
require_once __DIR__ . '/../../core/repositories/BaseRepository.php';
require_once __DIR__ . '/../../core/repositories/UserRepository.php';
require_once __DIR__ . '/../../core/repositories/EventRepository.php';

class RepositoryPatternTest {
    private $db;
    private $userRepo;
    private $eventRepo;
    private $testResults = [];
    
    public function __construct() {
        $this->db = getDB();
        $this->userRepo = new UserRepository();
        $this->eventRepo = new EventRepository();
    }
    
    public function runTests(): array {
        echo "🧪 Running Repository Pattern Tests...\n";
        
        $this->testBaseRepositoryFunctionality();
        $this->testUserRepositorySpecifics();
        $this->testEventRepositorySpecifics();
        $this->testPerformanceIntegration();
        $this->testErrorHandling();
        $this->testTransactionSupport();
        
        return $this->testResults;
    }
    
    private function testBaseRepositoryFunctionality(): void {
        try {
            // Test database connection
            $connection = $this->userRepo->getConnection();
            $this->assert($connection instanceof PDO, 'BaseRepository has valid PDO connection');
            
            // Test performance manager integration
            $this->assert(method_exists($this->userRepo, 'executeOptimizedQuery'), 'BaseRepository has optimized query method');
            
            // Test cache integration
            $this->assert(method_exists($this->userRepo, 'getOptimizationRecommendations'), 'BaseRepository has optimization recommendations');
            
            echo "  ✅ BaseRepository functionality tests passed\n";
            
        } catch (Exception $e) {
            $this->recordTest('BaseRepository Functionality', false, $e->getMessage());
        }
    }
    
    private function testUserRepositorySpecifics(): void {
        try {
            // Test UserRepository instantiation
            $this->assert($this->userRepo instanceof UserRepository, 'UserRepository instantiates correctly');
            $this->assert($this->userRepo instanceof BaseRepository, 'UserRepository extends BaseRepository');
            
            // Test UserRepository specific methods
            $this->assert(method_exists($this->userRepo, 'findByEmail'), 'UserRepository has findByEmail method');
            $this->assert(method_exists($this->userRepo, 'findByUsername'), 'UserRepository has findByUsername method');
            $this->assert(method_exists($this->userRepo, 'findByRFIDTag'), 'UserRepository has findByRFIDTag method');
            
            echo "  ✅ UserRepository specific tests passed\n";
            
        } catch (Exception $e) {
            $this->recordTest('UserRepository Specifics', false, $e->getMessage());
        }
    }
    
    private function testEventRepositorySpecifics(): void {
        try {
            // Test EventRepository instantiation
            $this->assert($this->eventRepo instanceof EventRepository, 'EventRepository instantiates correctly');
            $this->assert($this->eventRepo instanceof BaseRepository, 'EventRepository extends BaseRepository');
            
            // Test EventRepository specific methods
            $this->assert(method_exists($this->eventRepo, 'findActiveEvents'), 'EventRepository has findActiveEvents method');
            $this->assert(method_exists($this->eventRepo, 'findEventsByDateRange'), 'EventRepository has findEventsByDateRange method');
            
            echo "  ✅ EventRepository specific tests passed\n";
            
        } catch (Exception $e) {
            $this->recordTest('EventRepository Specifics', false, $e->getMessage());
        }
    }
    
    private function testPerformanceIntegration(): void {
        try {
            // Test that repositories have performance manager
            $this->assert(property_exists($this->userRepo, 'performanceManager'), 'Repository has performance manager property');
            $this->assert(property_exists($this->userRepo, 'databaseOptimizer'), 'Repository has database optimizer property');
            
            echo "  ✅ Performance integration tests passed\n";
            
        } catch (Exception $e) {
            $this->recordTest('Performance Integration', false, $e->getMessage());
        }
    }
    
    private function testErrorHandling(): void {
        try {
            // Test error handling in repository methods
            $this->assert(method_exists($this->userRepo, 'logError'), 'Repository has error logging method');
            
            echo "  ✅ Error handling tests passed\n";
            
        } catch (Exception $e) {
            $this->recordTest('Error Handling', false, $e->getMessage());
        }
    }
    
    private function testTransactionSupport(): void {
        try {
            // Test transaction support
            $this->assert(method_exists($this->userRepo, 'beginTransaction'), 'Repository supports transactions');
            $this->assert(method_exists($this->userRepo, 'commit'), 'Repository can commit transactions');
            $this->assert(method_exists($this->userRepo, 'rollback'), 'Repository can rollback transactions');
            
            echo "  ✅ Transaction support tests passed\n";
            
        } catch (Exception $e) {
            $this->recordTest('Transaction Support', false, $e->getMessage());
        }
    }
    
    private function assert($condition, $message): void {
        if ($condition) {
            $this->recordTest($message, true);
        } else {
            $this->recordTest($message, false);
            throw new Exception("Assertion failed: {$message}");
        }
    }
    
    private function recordTest($name, $passed, $error = null): void {
        $this->testResults[] = [
            'name' => $name,
            'passed' => $passed,
            'error' => $error
        ];
    }
}
?>
