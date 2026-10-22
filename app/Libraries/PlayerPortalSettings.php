<?php

namespace App\Libraries;

use App\Models\ConfigModel;

class PlayerPortalSettings
{
    private const TEST_MODE_KEY = 'player_portal_test_mode';

    public function isTestMode(): bool
    {
        $row = (new ConfigModel())->where('clave', self::TEST_MODE_KEY)->first();
        return $row && (string) ($row['valor'] ?? '') === '1';
    }

    public function setTestMode(bool $enabled): bool
    {
        $model = new ConfigModel();
        $row = $model->where('clave', self::TEST_MODE_KEY)->first();
        $value = $enabled ? '1' : '0';
        return $row ? (bool) $model->update($row['id'], ['valor' => $value]) : (bool) $model->insert(['clave' => self::TEST_MODE_KEY, 'valor' => $value]);
    }
}
