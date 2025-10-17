<?php
declare(strict_types=1);

namespace App\Services\RFID;

use App\Models\User;
use App\Repositories\UserRepository;

class RfidService
{
    private array $activeSessions = [];
    
    public function __construct(
        private UserRepository $userRepository
    ) {}
    
    public function processCard(string $cardId, string $readerId): RfidScanResult
    {
        // Prevent double scans within 5 seconds
        $sessionKey = $cardId . '_' . $readerId;
        if (isset($this->activeSessions[$sessionKey])) {
            $lastScan = $this->activeSessions[$sessionKey];
            if (time() - $lastScan < 5) {
                return new RfidScanResult(false, 'Please wait before scanning again');
            }
        }
        
        $this->activeSessions[$sessionKey] = time();
        
        // Find user by RFID card
        $user = $this->userRepository->findByRfidCard($cardId);
        
        if (!$user) {
            return new RfidScanResult(false, 'Card not registered', null, $cardId);
        }
        
        if (!$user->isActive()) {
            return new RfidScanResult(false, 'User account is not active', $user);
        }
        
        return new RfidScanResult(true, 'Card recognized', $user, $cardId);
    }
    
    public function assignCardToUser(User $user, string $cardId): bool
    {
        // Check if card is already assigned
        $existingUser = $this->userRepository->findByRfidCard($cardId);
        if ($existingUser && $existingUser->getId() !== $user->getId()) {
            throw new \RuntimeException('Card already assigned to another user');
        }
        
        $user->setRfidCard($cardId);
        $this->userRepository->save($user);
        
        return true;
    }
    
    public function removeCardFromUser(User $user): bool
    {
        $user->setRfidCard(null);
        $this->userRepository->save($user);
        
        return true;
    }
    
    public function validateCardFormat(string $cardId): bool
    {
        // Basic validation - adjust based on your RFID card format
        return preg_match('/^[A-Fa-f0-9]{8,16}$/', $cardId) === 1;
    }
}

class RfidScanResult
{
    public function __construct(
        private bool $success,
        private string $message,
        private ?User $user = null,
        private ?string $cardId = null
    ) {}
    
    public function isSuccess(): bool { return $this->success; }
    public function getMessage(): string { return $this->message; }
    public function getUser(): ?User { return $this->user; }
    public function getCardId(): ?string { return $this->cardId; }
}
