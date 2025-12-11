# System Rekomendacji Książek

![Test Coverage](https://img.shields.io/badge/coverage-56%25-yellow)

Aplikacja rekomendacji książek oparta na sztucznej inteligencji, wykorzystująca embeddings OpenAI do dopasowania preferencji użytkowników.


## 🚀 Technologie

- **PHP 8.4** - Język backend
- **Symfony 8.0** - Framework PHP
- **MySQL 8.4** - Relacyjna baza danych
- **Qdrant** - Baza wektorowa dla szybkiego wyszukiwania podobieństwa
- **HTMX** - Dynamiczne interfejsy bez JavaScript
- **OpenAI API** - Embeddings tekstowe (text-embedding-3-small)
- **Tailwind CSS** - Framework CSS dla responsywnych interfejsów

## ✨ Funkcjonalności

### Dla użytkowników:
- ✅ Rejestracja i logowanie z bezpieczeństwem (throttling, rate limiting)
- ✅ Tworzenie rekomendacji książek z opisem (30-500 znaków)
- ✅ Automatyczne generowanie embeddingów przez OpenAI API
- ✅ Wybór tagów z inteligentnym wyszukiwaniem
- ✅ Wyświetlanie rekomendacji książek z lokalnymi placeholderami

### Dla administratorów:
- ✅ Komenda do czyszczenia danych książek przed embeddingami: `app:clean:ebooks-data`
- ✅ Komenda do przetwarzania wsadowego embeddingów książek: `app:process:ebook-embeddings`
- ✅ Migracja embeddingów książek do Qdrant: `app:migrate:ebook-embeddings-to-qdrant`
- ✅ Testowanie funkcjonalności Qdrant: `app:test:qdrant`
- ✅ Testowanie embeddingów OpenAI: `app:test:embedding`
- ✅ Zarządzanie użytkownikami
- ✅ Reset hasła użytkowników

## 🔧 Konfiguracja środowiska

### Wymagane zmienne środowiskowe:

```bash
# OpenAI API
OPENAI_API_KEY=twój-klucz-openai-api-tutaj
OPENAI_MODEL=text-embedding-3-small

# Baza wektorowa Qdrant
QDRANT_HOST=localhost
QDRANT_PORT=6333

# Baza danych (w config.env)
DATABASE_URL=mysql://użytkownik:hasło@host:port/baza_danych
```

### Instalacja:

```bash
# Zainstaluj zależności
composer install

# Uruchom z Docker (development)
./bin/run.sh ./bin/console doctrine:migrations:migrate
./bin/run.sh ./bin/console doctrine:fixtures:load
./bin/run.sh ./bin/console app:seed:tags

# Uruchom serwer (development)
./bin/run.sh symfony serve
```

**Uwaga:** W środowisku produkcyjnym użyj `./bin/run.sh` dla kompatybilności z istniejącymi skryptami Docker. Skrypt automatycznie wykrywa środowisko i wykonuje komendy odpowiednio.

## 🚀 Wdrożenie produkcyjne

### Wymagania serwera produkcyjnego

- **PHP 8.4+** z rozszerzeniami: `pdo_mysql`, `mbstring`, `xml`, `curl`
- **MySQL 8.4+** lub kompatybilna baza danych
- **Qdrant** - baza wektorowa (może być uruchomiona w Docker)
- **Nginx/Apache** z konfiguracją dla Symfony
- **Composer** do instalacji zależności

### Konfiguracja produkcji

1. **Przygotuj środowisko:**
   ```bash
   # Sklonuj repozytorium
   git clone <repository-url>
   cd books-recommender

   # Zainstaluj zależności PHP
   composer install --no-dev --optimize-autoloader

   # Skopiuj konfigurację środowiska
   cp config.prod.env .env
   # Edytuj .env z właściwymi wartościami
   ```

2. **Konfiguracja bazy danych:**
   ```bash
   # Uruchom migracje
   APP_ENV=prod ./bin/console doctrine:migrations:migrate --no-interaction

   # Wypełnij bazę danymi początkowymi (jeśli potrzebne)
   APP_ENV=prod ./bin/console doctrine:fixtures:load --no-interaction
   ```

3. **Przygotuj zasoby:**
   ```bash
   # Zbuduj zasoby CSS/JS
   npm install
   npm run build-prod

   # Wyczyść i ogrzej cache Symfony
   APP_ENV=prod ./bin/console cache:clear
   APP_ENV=prod ./bin/console cache:warmup
   ```

### Konfiguracja Nginx (przykład)

```nginx
server {
    listen 80;
    server_name your-domain.com;
    root /path/to/books-recommender/public;

    location / {
        try_files $uri $uri/ /index.php$is_args$args;
    }

    location ~ ^/index\.php(/|$) {
        fastcgi_pass unix:/var/run/php/php8.4-fpm.sock;
        fastcgi_split_path_info ^(.+\.php)(/.*)$;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        fastcgi_param DOCUMENT_ROOT $realpath_root;
    }

    # Cache dla statycznych zasobów
    location ~* \.(css|js|png|jpg|jpeg|gif|ico|svg|woff|woff2|ttf|eot)$ {
        expires 1y;
        add_header Cache-Control "public, immutable";
    }
}
```

### Rozwiązywanie problemów produkcyjnych

#### Sesje i uwierzytelnianie
- Upewnij się, że `session.save_path` jest zapisywalny
- Sprawdź konfigurację `session.cookie_secure` dla HTTPS
- Weryfikuj ustawienia `session.cookie_samesite`

#### Cache i wydajność
- Użyj Redis/Memcached dla cache Symfony jeśli to możliwe
- Skonfiguruj reverse proxy (Varnish/Nginx) dla statycznych zasobów
- Monitoruj użycie pamięci i optymalizuj autoloader

#### Bezpieczeństwo
- Włącz HTTPS z prawidłowym certyfikatem
- Skonfiguruj Content Security Policy (CSP) - aplikacja jest w pełni zgodna
- Regularnie aktualizuj zależności bezpieczeństwa
- Bezpieczne zarządzanie sesjami i cookies

## 📊 Architektura

### Główne komponenty:
- **RecommendationService** - Logika biznesowa dla rekomendacji i wyszukiwania podobnych książek
- **OpenAIEmbeddingClient** - Klient OpenAI API do generowania embeddingów
- **EbookEmbeddingService** - Zarządzanie embeddingami książek w Qdrant
- **QdrantClient** - Klient bazy wektorowej Qdrant
- **TextNormalizationService** - Normalizacja tekstu użytkownika
- **TagService** - Zarządzanie tagami książek

### Bazy danych:

#### MySQL (dane relacyjne):
- **users** - Użytkownicy systemu
- **recommendations** - Rekomendacje użytkowników
- **recommendations_embeddings** - Embeddings OpenAI dla rekomendacji użytkowników
- **ebooks** - Katalog książek z metadanymi (ISBN VARCHAR(13), main_description, tags)
- **ebooks_embeddings** - Embeddings książek z payload (ebook_id jako ISBN, payload_description)
- **tags** - Tagi kategorii książek
- **recommendations_tags** - Relacja wiele-do-wielu między rekomendacjami a tagami

#### Qdrant (baza wektorowa):
- **ebooks** - Kolekcja embeddingów książek dla szybkiego wyszukiwania wektorowego
- **recommendations** - Embeddings użytkowników (tylko MySQL dla optymalizacji)

## 🔄 Proces rekomendacji

### Tworzenie rekomendacji:
1. Użytkownik wprowadza opis książki (30-500 znaków)
2. Tekst jest normalizowany i tworzony hash SHA256
3. Jeśli embedding nie istnieje, pobierany jest z OpenAI API
4. Embedding jest cachowany w MySQL (`recommendations_embeddings`)
5. Rekomendacja jest zapisywana z wybranymi tagami

### Wyszukiwanie podobnych książek:
1. Na podstawie opisu rekomendacji użytkownika generowany jest embedding
2. Embedding użytkownika jest używany jako zapytanie do wyszukiwania w Qdrant
3. Qdrant zwraca książki z najwyższym podobieństwem cosinusowym
4. Wyniki są filtrowane i zwracane użytkownikowi

### Architektura optymalizacji:
- **Embeddings użytkowników**: Przechowywane tylko w MySQL (oszczędność zasobów)
- **Embeddings książek**: Synchronizowane między MySQL i Qdrant (szybkie wyszukiwanie)
- **Wyszukiwanie**: Embedding zapytania → Qdrant → podobieństwo cosinusowe → wyniki

## 🧪 Testy

```bash
# Wszystkie testy
./bin/run.sh ./bin/phpunit

# Testy wybranego modułu
./bin/run.sh ./bin/phpunit --filter OpenAIEmbeddingClientTest
./bin/run.sh ./bin/phpunit --filter RecommendationServiceTest

# Integracja z usługami zewnętrznymi
./bin/run.sh ./bin/console app:test:embedding "tekst testowy"
./bin/run.sh ./bin/console app:test:qdrant --create-test-data

# Pokrycie kodu testami
./bin/run.sh ./bin/phpunit --coverage-html=var/coverage-html

# Raport pokrycia w konsoli
./bin/run.sh ./bin/phpunit --coverage-text
```


### Przygotowanie danych do embeddingów:
```bash
# Czyszczenie danych książek przed przetwarzaniem embeddingów
./bin/run.sh ./bin/console app:clean:ebooks-data --batch-size=10

# Czyszczenie z limitem iteracji
./bin/run.sh ./bin/console app:clean:ebooks-data --batch-size=10 --max-iterations=50

# Czyszczenie w trybie suchej próby
./bin/run.sh ./bin/console app:clean:ebooks-data --dry-run
```

### Migracja danych:
```bash
# Migracja embeddingów książek do Qdrant
./bin/run.sh ./bin/console app:migrate:ebook-embeddings-to-qdrant

# Sprawdź statystyki kolekcji w Qdrant
./bin/run.sh ./bin/console app:migrate:ebook-embeddings-to-qdrant --stats-only
```

## 🆕 Najnowsze zmiany (v2.1)

### Wdrożenie produkcyjne i stabilność:
- ✅ **Pełne wsparcie dla produkcji** - kompletna dokumentacja wdrożenia produkcyjnego
- ✅ **Rozwiązanie problemów z Varnish** - konfiguracja cache dla aplikacji Symfony
- ✅ **Naprawa problemów z sesjami** - poprawiona konfiguracja sesji dla środowisk produkcyjnych
- ✅ **Zgodność z CSP** - usunięcie inline event handlers, dodanie 'unsafe-hashes'
- ✅ **Optymalizacja bezpieczeństwa** - poprawiona konfiguracja cookies i HTTPS

### Usprawnienia UX/UI:
- ✅ **Responsywne formularze autoryzacji** - login/register pozycjonowane u góry strony
- ✅ **Czysty interfejs** - usunięte zbędne elementy debugowania z produkcji
- ✅ **Poprawione tłumaczenia** - naprawione klucze tłumaczeń dla przycisków

### Architektura i wydajność:
- ✅ **Usunięcie zależności zewnętrznych** - eliminacja zewnętrznych serwisów obrazów
- ✅ **Optymalizacja cache** - inteligentne cachowanie statycznych zasobów
- ✅ **Bezpieczeństwo kontentu** - Content Security Policy w pełni funkcjonalna
- ✅ **Lepsze zarządzanie zasobami** - optymalizacja pamięci i autoloadera

## 🆕 Najnowsze zmiany (v2.0)

### Wydajność i bezpieczeństwo:
- ✅ **Inteligentne przetwarzanie wsadowe** - komenda `app:clean:ebooks-data` przetwarza książki w małych paczkach z automatycznym zarządzaniem błędami
- ✅ **Mechanizm bezpieczeństwa** - automatyczne przerwanie przetwarzania przy systematycznych błędach
- ✅ **Optymalizacja pamięci** - EntityManager jest czyszczony po każdej iteracji

### Usprawnienia struktury danych:
- ✅ **ISBN jako identyfikator** - książki identyfikowane przez 13-cyfrowy kod ISBN zamiast ID
- ✅ **Bogatsze metadane** - dodano pola `main_description` i `tags` do tabeli ebooks
- ✅ **Payload rozszerzony** - dodano `payload_description` do embeddingów książek

### Architektura:
- ✅ **Lepsze zarządzanie embeddingami** - synchronizacja między MySQL i Qdrant po ISBN
- ✅ **Bezpieczeństwo transakcji** - obsługa błędów bez przerywania całego procesu

## 🤝 Współtworzenie

1. Zrób fork projektu
2. Utwórz gałąź dla swojej funkcjonalności (`git checkout -b feature/NiesamowitaFunkcjonalnosc`)
3. Zacommituj swoje zmiany (`git commit -m 'Dodaj jakąś NiesamowitąFunkcjonalność'`)
4. Wypchnij do swojej gałęzi (`git push origin feature/NiesamowitaFunkcjonalnosc`)
5. Otwórz Pull Request
