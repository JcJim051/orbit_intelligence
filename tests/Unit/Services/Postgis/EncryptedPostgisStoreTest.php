<?php

namespace Tests\Unit\Services\Postgis;

use App\Services\Postgis\EncryptedPostgisStore;
use Illuminate\Encryption\Encrypter;
use Illuminate\Filesystem\Filesystem;
use PHPUnit\Framework\TestCase;

class EncryptedPostgisStoreTest extends TestCase
{
    private string $path;

    protected function setUp(): void
    {
        parent::setUp();
        $this->path = sys_get_temp_dir().'/siid-postgis-store-'.getmypid().'.enc';
        (new Filesystem)->delete($this->path);
    }

    protected function tearDown(): void
    {
        (new Filesystem)->delete($this->path);
        parent::tearDown();
    }

    public function test_it_persists_credentials_encrypted_and_recovers_the_original_values(): void
    {
        $store = new EncryptedPostgisStore(
            $this->path,
            new Encrypter(str_repeat('k', 32), 'AES-256-CBC'),
        );
        $configuration = [
            'host' => '127.0.0.1',
            'database' => 'siid_meta',
            'admin_password' => 'a-private-password',
            'active' => false,
        ];

        $store->write($configuration);

        $ciphertext = (new Filesystem)->get($this->path);
        $this->assertStringNotContainsString('a-private-password', $ciphertext);
        $this->assertSame($configuration, $store->read());
    }
}
