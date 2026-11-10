<?php

declare(strict_types=1);

use App\Exceptions\ImageProcessingException;
use App\Models\User;
use App\Notifications\AccountDeletionRequestedNotification;
use App\Notifications\EmailChangedOldAddressNotification;
use App\Notifications\MfaResetByAdminNotification;
use App\Notifications\PasswordChangedNotification;
use App\Notifications\UserDeletionRequestedForAdminNotification;
use App\Notifications\UserEmailChangedForAdminNotification;

it('renders security emails in the recipient locale', function () {
    $en = User::factory()->make(['name' => 'Alice', 'locale' => 'en']);
    $ru = User::factory()->make(['name' => 'Алиса', 'locale' => 'ru']);

    $enMail = (new PasswordChangedNotification('1.2.3.4', 'Test Agent', '2026-11-04 12:00'))->toMail($en);
    $ruMail = (new PasswordChangedNotification('1.2.3.4', 'Test Agent', '2026-11-04 12:00'))->toMail($ru);

    expect($enMail->subject)->toBe('Password changed')
        ->and($enMail->greeting)->toBe('Hello!')
        ->and($enMail->introLines)->toContain('When: 2026-11-04 12:00')
        ->and($enMail->actionText)->toBe('Reset password')
        ->and($ruMail->subject)->toBe('Пароль изменён')
        ->and($ruMail->greeting)->toBe('Здравствуйте!')
        ->and($ruMail->introLines)->toContain('Когда: 2026-11-04 12:00')
        ->and($ruMail->actionText)->toBe('Сбросить пароль');
});

it('renders every localized mail notification in both locales', function () {
    $en = User::factory()->make(['name' => 'Alice', 'locale' => 'en']);
    $ru = User::factory()->make(['name' => 'Алиса', 'locale' => 'ru']);
    $target = User::factory()->make(['name' => 'Bob']);

    $notifications = [
        new EmailChangedOldAddressNotification($target, 'old@example.com', 'new@example.com', '1.2.3.4', 'Agent'),
        new MfaResetByAdminNotification($target),
        new AccountDeletionRequestedNotification('Testing'),
        new UserDeletionRequestedForAdminNotification($target, 'Testing', '1.2.3.4', 'Agent'),
        new UserEmailChangedForAdminNotification($target, 'old@example.com', 'new@example.com', '1.2.3.4', 'Agent'),
    ];

    foreach ($notifications as $notification) {
        $enSubject = (string) $notification->toMail($en)->subject;
        $ruSubject = (string) $notification->toMail($ru)->subject;

        expect($enSubject)->not->toBe('')
            ->and($ruSubject)->not->toBe('')
            ->and($enSubject)->not->toBe($ruSubject);
    }
});

it('falls back to the application locale for a user without a preference', function () {
    $user = User::factory()->make(['locale' => null]);

    $mail = (new PasswordChangedNotification('1.2.3.4', 'Agent'))->toMail($user);

    expect($mail->subject)->toBe((string) trans('site.notifications.mail.password_changed.subject', [], 'ru'));
});

it('translates image processing exception messages', function () {
    app()->setLocale('en');

    expect(ImageProcessingException::unsupportedFormat('image/gif')->getMessage())
        ->toBe('Unsupported image format: image/gif.')
        ->and(ImageProcessingException::tooLarge(1024, 512)->getMessage())
        ->toBe('The file is too large (1024 bytes, limit 512).')
        ->and(ImageProcessingException::tooManyPixels(100, 50)->getMessage())
        ->toBe('The resolution is too large (100 px, limit 50).')
        ->and(ImageProcessingException::tooSmall(10, 20, 50)->getMessage())
        ->toBe('The image is too small (10x20, minimum 50x50).')
        ->and(ImageProcessingException::unableToRead()->getMessage())
        ->toBe('The file could not be read as an image.');

    app()->setLocale('ru');

    expect(ImageProcessingException::unsupportedFormat('image/gif')->getMessage())
        ->toBe('Недопустимый формат изображения: image/gif.')
        ->and(ImageProcessingException::tooSmall(10, 20, 50)->getMessage())
        ->toBe('Изображение слишком маленькое (10x20, минимум 50x50).')
        ->and(ImageProcessingException::unableToRead()->getMessage())
        ->toBe('Не удалось прочитать файл как изображение.');
});
