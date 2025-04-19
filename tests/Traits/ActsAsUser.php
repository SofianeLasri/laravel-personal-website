<?php

namespace Tests\Traits;

use App\Models\User;
use Illuminate\Foundation\Testing\Concerns\InteractsWithAuthentication;

trait ActsAsUser
{
    use InteractsWithAuthentication;

    protected User $user;

    /**
     * Authentifie un utilisateur pour les tests
     *
     * @param  string  $role  Le rôle de l'utilisateur (admin, reader, etc.)
     */
    protected function loginAs(string $role = 'admin'): void
    {
        $this->user = User::factory()->create();

        $this->actingAs($this->user);
    }

    /**
     * Authentifie un utilisateur admin pour les tests
     */
    protected function loginAsAdmin(): void
    {
        $this->loginAs('admin');
    }

    /**
     * Authentifie un utilisateur lecteur pour les tests
     */
    protected function loginAsReader(): void
    {
        $this->loginAs('reader');
    }
}
