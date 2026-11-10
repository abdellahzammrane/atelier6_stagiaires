<?php
function getDatabase(): PDO
{
    $databaseUrl = getenv('DATABASE_URL');

    if ($databaseUrl !== false && $databaseUrl !== '') {
        $parts = parse_url($databaseUrl);
        if (!is_array($parts) || ($parts['scheme'] ?? '') !== 'mysql') {
            throw new RuntimeException('DATABASE_URL must be a valid mysql:// URL.');
        }

        $host = $parts['host'] ?? '';
        $port = $parts['port'] ?? 3306;
        $name = rawurldecode(ltrim($parts['path'] ?? '', '/'));
        $user = rawurldecode($parts['user'] ?? '');
        $password = rawurldecode($parts['pass'] ?? '');
    } else {
        $required = ['DB_HOST', 'DB_NAME', 'DB_USER', 'DB_PASSWORD'];
        $missing = [];

        foreach ($required as $key) {
            $value = getenv($key);
            if ($value === false || $value === '') {
                $missing[] = $key;
            }
        }

        if ($missing) {
            throw new RuntimeException('Missing database environment variables: ' . implode(', ', $missing));
        }

        $host = getenv('DB_HOST');
        $port = getenv('DB_PORT') ?: '3306';
        $name = getenv('DB_NAME');
        $user = getenv('DB_USER');
        $password = getenv('DB_PASSWORD');
    }

    if ($host === '' || $name === '' || $user === '') {
        throw new RuntimeException('The database URL must include a host, database name, and username.');
    }

    $caPath = getenv('DB_SSL_CA') ?: dirname(__DIR__) . '/certs/ca.pem';
    if (!is_readable($caPath)) {
        throw new RuntimeException('Database CA certificate is missing or unreadable: ' . $caPath);
    }

    $dsn = "mysql:host=$host;port=$port;dbname=$name;charset=utf8mb4";
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::MYSQL_ATTR_SSL_CA => $caPath,
        PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT => true
    ];

    return new PDO($dsn, $user, $password, $options);
}
