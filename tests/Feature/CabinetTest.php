<?php

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

function makeCabinetUser(): User
{
    $user = User::factory()->create(['email_verified_at' => now()]);
    $user->assignRole('user');

    return $user;
}

function makeAvatarJpeg(int $width = 400, int $height = 400): string
{
    $image = imagecreatetruecolor($width, $height);
    $color = imagecolorallocate($image, 200, 100, 50);
    imagefill($image, 0, 0, $color);

    $path = tempnam(sys_get_temp_dir(), 'avatar_') . '.jpg';
    imagejpeg($image, $path, 90);
    imagedestroy($image);

    return $path;
}

beforeEach(function () {
    Storage::fake('public');
});

it('redirects guests from cabinet to login', function () {
    $this->get(route('cabinet.show'))->assertRedirect(route('login'));
    $this->get(route('cabinet.tab', ['tab' => 'profile']))->assertRedirect(route('login'));
});

it('shows profile tab to authenticated user', function () {
    $user = makeCabinetUser();

    $response = $this->actingAs($user)->get(route('cabinet.tab', ['tab' => 'profile']));

    $response->assertOk();
    $response->assertSee('Профиль');
    $response->assertSee($user->name);
});

it('returns 404 for unknown tab', function () {
    $user = makeCabinetUser();

    $this->actingAs($user)
        ->get(route('cabinet.tab', ['tab' => 'invalid']))
        ->assertNotFound();
});

it('updates profile fields', function () {
    $user = makeCabinetUser();

    $response = $this->actingAs($user)->post(route('cabinet.profile.update'), [
        'race' => 'elvaan',
        'main_job' => 'PLD',
        'legend' => '**Test** legend',
        'phone' => '+7 999 123-45-67',
        'phone_is_public' => '1',
        'is_profile_public' => '1',
    ]);

    $response->assertRedirect(route('cabinet.tab', ['tab' => 'profile']));

    $user->refresh();
    expect($user->race)->toBe('elvaan');
    expect($user->main_job)->toBe('PLD');
    expect($user->legend)->toBe('**Test** legend');
    expect($user->legend_html)->toContain('<strong>Test</strong>');
    expect($user->phone_is_public)->toBeTrue();
    expect($user->is_profile_public)->toBeTrue();
});

it('rejects invalid race', function () {
    $user = makeCabinetUser();

    $this->actingAs($user)->post(route('cabinet.profile.update'), [
        'race' => 'viera',
    ])->assertSessionHasErrors('race');
});

it('uploads avatar and creates two webp variants', function () {
    $user = makeCabinetUser();

    $path = makeAvatarJpeg(600, 600);
    $file = new UploadedFile($path, 'avatar.jpg', 'image/jpeg', null, true);

    $response = $this->actingAs($user)->post(route('cabinet.avatar.upload'), [
        'avatar' => $file,
    ]);

    $response->assertRedirect(route('cabinet.tab', ['tab' => 'profile']));

    $user->refresh();
    expect($user->avatar_path)->toEndWith('_full.webp');
    Storage::disk('public')->assertExists($user->avatar_path);

    @unlink($path);
});

it('rejects non-image avatar', function () {
    $user = makeCabinetUser();

    $path = tempnam(sys_get_temp_dir(), 'txt_') . '.txt';
    file_put_contents($path, 'not an image');

    $file = new UploadedFile($path, 'avatar.txt', 'text/plain', null, true);

    $response = $this->actingAs($user)->post(route('cabinet.avatar.upload'), [
        'avatar' => $file,
    ]);

    $response->assertSessionHasErrors('avatar');

    @unlink($path);
});

it('deletes avatar', function () {
    $user = makeCabinetUser();

    $path = makeAvatarJpeg();
    $file = new UploadedFile($path, 'avatar.jpg', 'image/jpeg', null, true);

    $this->actingAs($user)->post(route('cabinet.avatar.upload'), ['avatar' => $file]);

    $user->refresh();
    $storedPath = $user->avatar_path;
    expect($storedPath)->not->toBeNull();

    $this->actingAs($user)->delete(route('cabinet.avatar.delete'))->assertRedirect();

    $user->refresh();
    expect($user->avatar_path)->toBeNull();
    Storage::disk('public')->assertMissing($storedPath);

    @unlink($path);
});
