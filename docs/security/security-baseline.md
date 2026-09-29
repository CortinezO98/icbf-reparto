# Línea base de seguridad

1. No almacenar secretos en Git.
2. No usar credenciales productivas en desarrollo.
3. Password hashing mediante Argon2id/PASSWORD_DEFAULT.
4. CSRF obligatorio para operaciones de escritura desde navegador.
5. PDO con emulación de prepares deshabilitada.
6. Autorización del lado servidor por permiso.
7. Cookies de sesión HttpOnly y SameSite.
8. Regeneración de sesión después del login.
9. Rate limiting de login.
10. Nginx publica únicamente `public/`.
11. Base de datos sin puerto publicado al host.
12. Errores detallados solo si `APP_DEBUG=1`.
13. Acciones de seguridad y administración registradas en auditoría.
14. Archivos operativos se almacenarán fuera del webroot.
