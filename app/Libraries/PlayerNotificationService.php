<?php

namespace App\Libraries;

use App\Models\PlayerNotificationsModel;

class PlayerNotificationService
{
    private $db;
    private $model;

    public function __construct()
    {
        $this->db = \Config\Database::connect();
        $this->model = new PlayerNotificationsModel();
    }

    public function isAvailable(): bool
    {
        return $this->db->tableExists('player_notifications');
    }

    public function create(int $playerId, string $type, string $title, string $message, string $url = ''): bool
    {
        if ($playerId <= 0 || ! $this->isAvailable()) {
            return false;
        }

        try {
            return (bool) $this->model->insert([
                'player_id' => $playerId,
                'type' => $type,
                'title' => $title,
                'message' => $message,
                'url' => $url !== '' ? ltrim($url, '/') : null,
                'is_read' => 0,
                'created_at' => date('Y-m-d H:i:s'),
            ]);
        } catch (\Throwable $exception) {
            log_message('error', 'Portal de jugadores: no se pudo guardar una notificación: ' . $exception->getMessage());
            return false;
        }
    }

    public function forPlayer(int $playerId, int $limit = 8): array
    {
        if ($playerId <= 0 || ! $this->isAvailable()) {
            return [];
        }

        return $this->model->where('player_id', $playerId)
            ->orderBy('created_at', 'DESC')
            ->findAll(max(1, min($limit, 30)));
    }

    public function unreadCount(int $playerId): int
    {
        if ($playerId <= 0 || ! $this->isAvailable()) {
            return 0;
        }

        return (int) $this->model->where('player_id', $playerId)->where('is_read', 0)->countAllResults();
    }

    public function afterId(int $playerId, int $lastId, int $limit = 10): array
    {
        if ($playerId <= 0 || ! $this->isAvailable()) {
            return [];
        }

        return $this->model->where('player_id', $playerId)
            ->where('id >', max(0, $lastId))
            ->orderBy('id', 'ASC')
            ->findAll(max(1, min($limit, 20)));
    }

    public function markRead(int $notificationId, int $playerId): bool
    {
        if ($notificationId <= 0 || $playerId <= 0 || ! $this->isAvailable()) {
            return false;
        }

        return (bool) $this->model->where('id', $notificationId)->where('player_id', $playerId)->set(['is_read' => 1])->update();
    }

    public function findForPlayer(int $notificationId, int $playerId): ?array
    {
        if ($notificationId <= 0 || $playerId <= 0 || ! $this->isAvailable()) {
            return null;
        }

        return $this->model->where('id', $notificationId)->where('player_id', $playerId)->first() ?: null;
    }
}
