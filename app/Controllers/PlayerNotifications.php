<?php

namespace App\Controllers;

use App\Libraries\PlayerNotificationService;

class PlayerNotifications extends BaseController
{
    public function latest()
    {
        $playerId = (int) session()->get('player_id');
        if ($playerId <= 0) {
            return $this->response->setStatusCode(401)->setJSON(['notifications' => [], 'unread' => 0]);
        }

        $lastId = (int) $this->request->getGet('desde');
        $service = new PlayerNotificationService();
        return $this->response->setJSON([
            'notifications' => $service->afterId($playerId, $lastId),
            'unread' => $service->unreadCount($playerId),
        ]);
    }

    public function read(int $notificationId)
    {
        $playerId = (int) session()->get('player_id');
        if ($playerId <= 0) {
            return redirect()->to(base_url('app/login'));
        }

        $service = new PlayerNotificationService();
        $notification = $service->findForPlayer($notificationId, $playerId);
        if (! $notification) {
            return redirect()->to(base_url('app'));
        }

        $service->markRead($notificationId, $playerId);
        $url = trim((string) ($notification['url'] ?? ''));
        if (strpos($url, 'app/') !== 0) {
            $url = 'app/mis-partidos';
        }

        return redirect()->to(base_url($url));
    }
}
