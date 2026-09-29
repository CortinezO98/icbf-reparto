<?php
declare(strict_types=1);

namespace App\Auth;

final class PasswordPolicy
{
    /** @return list<string> */
    public static function validate(string $password): array
    {
        $errors = [];

        if (mb_strlen($password) < 12) {
            $errors[] = 'La contraseña debe tener al menos 12 caracteres.';
        }
        if (!preg_match('/[a-z]/', $password)) {
            $errors[] = 'Debe incluir al menos una letra minúscula.';
        }
        if (!preg_match('/[A-Z]/', $password)) {
            $errors[] = 'Debe incluir al menos una letra mayúscula.';
        }
        if (!preg_match('/\d/', $password)) {
            $errors[] = 'Debe incluir al menos un número.';
        }
        if (!preg_match('/[^\pL\d]/u', $password)) {
            $errors[] = 'Debe incluir al menos un símbolo.';
        }

        return $errors;
    }

    public static function hash(string $password): string
    {
        $algo = defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_DEFAULT;
        $hash = password_hash($password, $algo);

        return $hash;
    }
}
