<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RoleAndPermissionSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permissions = [
            // Sections
            'section.home.view',
            'section.news.view',
            'section.gallery.view',
            'section.contacts.view',
            'section.events.view',
            'section.players.view',
            'section.player_profiles.view',

            // News
            'news.manage_site',
            'news.create_own',
            'news.edit_own',
            'news.delete_own',
            'news.edit_any',
            'news.delete_any',
            'news.moderate',

            // Gallery
            'albums.manage_site',
            'albums.create_own',
            'albums.edit_own',
            'albums.delete_own',
            'photos.upload_own',
            'photos.edit_own',
            'photos.delete_any',

            // Comments
            'comments.create',
            'comments.moderate',

            // Chat
            'chat.participate',
            'chat.moderate',

            // Events
            'events.create',
            'events.join',
            'events.manage_any',

            // Misc
            'pages.manage',
            'dashboard.view',
            'profile.edit_own',
            'panel.access',
            'users.manage',
            'roles.manage',
            'matrix.manage',
            'settings.manage',
            'widgets.manage',
            'audit.view',
            'guests.view',
            'ranks.manage',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        $admin = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $editor = Role::firstOrCreate(['name' => 'editor', 'guard_name' => 'web']);
        $user = Role::firstOrCreate(['name' => 'user', 'guard_name' => 'web']);

        // Admin — все права
        $admin->syncPermissions($permissions);

        // User — базовый набор
        $userPermissions = [
            'section.home.view',
            'section.news.view',
            'section.gallery.view',
            'section.contacts.view',
            'section.events.view',
            'section.players.view',
            'section.player_profiles.view',
            'news.create_own',
            'news.edit_own',
            'news.delete_own',
            'albums.create_own',
            'albums.edit_own',
            'albums.delete_own',
            'photos.upload_own',
            'photos.edit_own',
            'comments.create',
            'chat.participate',
            'events.create',
            'events.join',
            'dashboard.view',
            'profile.edit_own',
        ];

        $user->syncPermissions($userPermissions);

        // Editor = User + контентные права (per frontend-spec §3.1)
        $editorContentPermissions = [
            'news.manage_site',
            'news.edit_any',
            'news.delete_any',
            'news.moderate',
            'albums.manage_site',
            'photos.delete_any',
            'comments.moderate',
            'events.manage_any',
            'pages.manage',
            'panel.access',
        ];

        $editor->syncPermissions(array_merge($userPermissions, $editorContentPermissions));

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
