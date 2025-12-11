1. Lista tabel z ich kolumnami, typami danych i ograniczeniami

```sql
CREATE TABLE users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  email VARCHAR(255) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  must_change_password BOOLEAN NOT NULL DEFAULT FALSE,
  banned BOOLEAN NOT NULL DEFAULT FALSE,
  suspended BOOLEAN NOT NULL DEFAULT FALSE,
  active BOOLEAN NOT NULL DEFAULT TRUE,
  last_login DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_polish_ci;
```

```sql
CREATE TABLE users_failed_logins (
  id INT AUTO_INCREMENT PRIMARY KEY,
  email VARCHAR(255) NOT NULL,
  attempts_count INT NOT NULL DEFAULT 1,
  last_attempt_at DATETIME NOT NULL,
  blocked_until DATETIME NULL,
  ip_address VARCHAR(45) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX email_idx (email),
  INDEX blocked_until_idx (blocked_until)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_polish_ci;
```

```sql
CREATE TABLE users_success_logins (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  ip_address VARCHAR(45) NOT NULL,
  user_agent VARCHAR(500) NOT NULL,
  login_type ENUM('user', 'admin') NOT NULL DEFAULT 'user',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_user_id (user_id),
  INDEX idx_created_at (created_at),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_polish_ci;
```

```sql
CREATE TABLE recommendations (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  short_description TEXT NOT NULL COLLATE utf8mb4_polish_ci,
  normalized_text_hash VARCHAR(64) NOT NULL UNIQUE,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_user_created (user_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_polish_ci;
```

```sql
CREATE TABLE recommendations_embeddings (
  id INT AUTO_INCREMENT PRIMARY KEY,
  normalized_text_hash VARCHAR(64) NOT NULL UNIQUE,
  description TEXT NOT NULL COLLATE utf8mb4_polish_ci,
  embedding JSON NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_normalized_hash (normalized_text_hash)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_polish_ci;
```

```sql
CREATE TABLE ebooks (
  id INT AUTO_INCREMENT PRIMARY KEY,
  isbn VARCHAR(13) NOT NULL UNIQUE,
  title VARCHAR(255) NOT NULL COLLATE utf8mb4_polish_ci,
  author VARCHAR(255) NOT NULL COLLATE utf8mb4_polish_ci,
  main_description TEXT NULL COLLATE utf8mb4_polish_ci,
  tags TEXT NULL,
  offers_count INT NOT NULL DEFAULT 0,
  comparison_link VARCHAR(512) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_isbn (isbn),
  INDEX idx_title_author (title, author)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_polish_ci;
```

```sql
CREATE TABLE ebooks_embeddings (
  id INT AUTO_INCREMENT PRIMARY KEY,
  ebook_id VARCHAR(13) NOT NULL,
  vector JSON NOT NULL,
  payload_title VARCHAR(255) NOT NULL COLLATE utf8mb4_polish_ci,
  payload_author VARCHAR(255) NOT NULL COLLATE utf8mb4_polish_ci,
  payload_tags JSON NOT NULL,
  payload_description TEXT NULL COLLATE utf8mb4_polish_ci,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uk_ebook_id (ebook_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_polish_ci;
```

```sql
CREATE TABLE tags (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(50) NOT NULL UNIQUE COLLATE utf8mb4_polish_ci,
  ascii VARCHAR(50) NOT NULL UNIQUE,
  active BOOLEAN NOT NULL DEFAULT TRUE,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_name (name),
  INDEX idx_ascii (ascii)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_polish_ci;
```

```sql
CREATE TABLE recommendations_tags (
  recommendation_id INT NOT NULL,
  tag_id INT NOT NULL,
  PRIMARY KEY (recommendation_id, tag_id),
  FOREIGN KEY (recommendation_id) REFERENCES recommendations(id) ON DELETE CASCADE,
  FOREIGN KEY (tag_id) REFERENCES tags(id) ON DELETE CASCADE,
  INDEX idx_tag_recommendation (tag_id, recommendation_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_polish_ci;
```

2. Relacje między tabelami

- `users` - `users_failed_logins`: Relacja jeden-do-wielu oparta na emailu (jeden użytkownik może mieć wiele nieudanych prób logowania)
- `users` - `users_success_logins`: Relacja jeden-do-wielu (jeden użytkownik może mieć wiele udanych prób logowania)
- `users` - `recommendations`: Relacja jeden-do-wielu (jeden użytkownik może mieć wiele rekomendacji)
- `recommendations` - `recommendations_embeddings`: Relacja jeden-do-jednego oparta na `normalized_text_hash` (każda rekomendacja ma dokładnie jeden embedding)
- `recommendations` - `tags`: Relacja wiele-do-wielu obsługiwana przez tabelę `recommendations_tags`
- `ebooks` - `ebooks_embeddings`: Relacja jeden-do-jednego oparta na ISBN (ebook_id w ebooks_embeddings zawiera ISBN książki z tabeli ebooks)

3. Indeksy

- `users.email` - unikalny indeks dla szybkiego wyszukiwania i logowania
- `users.updated_at` - indeks dla ewentualnych zapytań o ostatnio aktywnych użytkownikach
- `users_failed_logins.email` - indeks dla filtrowania prób logowania po emailu
- `users_failed_logins.blocked_until` - indeks dla sprawdzania czy użytkownik jest zablokowany
- `users_success_logins.user_id` - indeks dla filtrowania udanych prób logowania po użytkowniku
- `users_success_logins.created_at` - indeks dla wyszukiwania logowań w czasie
- `recommendations.user_id, created_at` - złożony indeks dla listowania rekomendacji użytkownika posortowanych po dacie
- `recommendations.normalized_text_hash` - unikalny indeks dla powiązania z embeddingami
- `recommendations_embeddings.normalized_text_hash` - indeks dla wyszukiwania embeddingów
- `ebooks.isbn` - unikalny indeks dla ISBN
- `ebooks.title, author` - złożony indeks dla wyszukiwania książek
- `ebooks_embeddings.ebook_id` - unikalny indeks dla ISBN książki
- `tags.name` - unikalny indeks dla nazw tagów
- `tags.ascii` - unikalny indeks dla wersji ASCII tagów (do URL)
- `recommendations_tags.recommendation_id, tag_id` - złożony indeks podstawowy dla relacji wiele-do-wielu
- `recommendations_tags.tag_id, recommendation_id` - indeks odwrotny dla wyszukiwania rekomendacji po tagach

4. Zasady MySQL (Row-Level Security - RLS) i rozwiązania alternatywne

MySQL nie posiada natywnego wsparcia dla RLS. Kontrola dostępu na poziomie wiersza jest implementowana poprzez:

1. **Parametryzacja zapytań w aplikacji** - wszystkie zapytania pobierające dane użytkownika zawierają warunek `WHERE user_id = ?` z ID zalogowanego użytkownika.

2. **Procedury składowane dla bezpiecznego dostępu**:

```sql
DELIMITER //

-- Procedura pobierająca rekomendacje dla konkretnego użytkownika
CREATE PROCEDURE get_user_recommendations(IN p_user_id INT)
BEGIN
    SELECT r.id, r.short_description, r.created_at,
           GROUP_CONCAT(t.name) as tags
    FROM recommendations r
    LEFT JOIN recommendations_tags rt ON r.id = rt.recommendation_id
    LEFT JOIN tags t ON rt.tag_id = t.id
    WHERE r.user_id = p_user_id
    GROUP BY r.id
    ORDER BY r.created_at DESC;
END //

-- Procedura sprawdzająca status użytkownika przed logowaniem
CREATE PROCEDURE validate_user_login(IN p_email VARCHAR(255))
BEGIN
    SELECT id, password_hash, active, banned, suspended, must_change_password
    FROM users
    WHERE email = p_email AND active = TRUE AND banned = FALSE AND suspended = FALSE;
END //

-- Procedura rejestrująca nieudaną próbę logowania
CREATE PROCEDURE log_failed_login(IN p_user_id INT, IN p_ip VARCHAR(45), IN p_type ENUM('user','admin'))
BEGIN
    INSERT INTO users_failed_logins (email, attempts_count, last_attempt_at, ip_address)
    VALUES (p_email, 1, NOW(), p_ip)
    ON DUPLICATE KEY UPDATE
        attempts_count = attempts_count + 1,
        last_attempt_at = NOW(),
        ip_address = p_ip;
END //

-- Procedura rejestrująca udaną próbę logowania
CREATE PROCEDURE log_success_login(IN p_user_id INT, IN p_ip VARCHAR(45), IN p_user_agent VARCHAR(500), IN p_type ENUM('user','admin'))
BEGIN
    INSERT INTO users_success_logins (user_id, ip_address, user_agent, login_type)
    VALUES (p_user_id, p_ip, p_user_agent, p_type);
END //

-- Procedura sprawdzająca czy użytkownik powinien być zablokowany (throttling)
CREATE PROCEDURE check_login_throttling(IN p_email VARCHAR(255))
BEGIN
    SELECT attempts_count, blocked_until
    FROM users_failed_logins
    WHERE email = p_email
    AND (blocked_until IS NULL OR blocked_until > NOW());
END //

DELIMITER ;
```

3. **Widoki dla uproszczonego dostępu** (jeśli potrzebne):

```sql
-- Widok rekomendacji z tagami dla administratora
CREATE VIEW admin_recommendations AS
SELECT r.*, u.email,
       GROUP_CONCAT(t.name) as tag_list
FROM recommendations r
JOIN users u ON r.user_id = u.id
LEFT JOIN recommendations_tags rt ON r.id = rt.recommendation_id
LEFT JOIN tags t ON rt.tag_id = t.id
GROUP BY r.id;
```

5. Dodatkowe uwagi lub wyjaśnienia dotyczące decyzji projektowych

- **Kodowanie znaków**: Wszystkie tabele używają domyślnie `ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_polish_ci` dla prawidłowej obsługi polskich znaków diakrytycznych.

- **Bezpieczeństwo**: Implementacja mechanizmu throttling poprzez śledzenie nieudanych prób logowania z tego samego IP. Flagi `active`, `banned`, `suspended` umożliwiają szczegółową kontrolę dostępu.

- **Optymalizacja wydajności**:
  - Złożone indeksy na często używanych kolumnach (`user_id + created_at`, `title + author`).
  - Relacja jeden-do-jednego między rekomendacjami a embeddingami oparta na hashu zamiast ID dla lepszej integralności danych.
  - Tabela `users_success_logins` umożliwia śledzenie udanych prób logowania dla celów audytowych.

- **Skalowalność**: Projekt pozwala na łatwe rozszerzenie systemu poprzez dodanie nowych profili użytkowników i tagów bez zmiany struktury tabel.

- **Integralność danych**: Klucze obce z `CASCADE DELETE` zapewniają automatyczne czyszczenie danych powiązanych. Unikalne indeksy zapobiegają duplikatom.

- **Wsparcie dla Qdrant**: Wektory książek są przechowywane w `ebooks_embeddings` i będą synchronizowane z bazą wektorową Qdrant dla szybkiego wyszukiwania podobieństwa. Książki są identyfikowane po ISBN (13-cyfrowym).

- **Walidacja danych**: Ograniczenia na poziomie bazy (UNIQUE, NOT NULL, CHECK constraints) wspierają logikę aplikacji przy walidacji opisów (30-500 znaków) i minimalnej liczby tagów.