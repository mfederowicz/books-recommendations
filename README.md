# Books Recommendations System

Aplikacja do rekomendacji książek oparta na sztucznej inteligencji, wykorzystująca embeddingi OpenAI do dopasowywania preferencji użytkowników.

## 🚀 Technologie

- **PHP 8.4** - Język backend
- **Symfony 8.0** - Framework PHP
- **MySQL 8.4** - Baza danych
- **HTMX** - Dynamiczne interfejsy bez JavaScript
- **OpenAI API** - Embeddingi tekstowe (text-embedding-3-small)

## ✨ Funkcjonalności

### Dla użytkowników:
- ✅ Rejestracja i logowanie z bezpieczeństwem (throttling, rate limiting)
- ✅ Tworzenie rekomendacji książkowych z opisem (30-500 znaków)
- ✅ Automatyczne generowanie embeddingów przez OpenAI API
- ✅ Wybór tagów z inteligentnym wyszukiwaniem
- ✅ Wyświetlanie rekomendacji książkowych

### Dla administratorów:
- ✅ Komenda do batch processing embeddingów książek: `app:process:ebook-embeddings`
- ✅ Zarządzanie użytkownikami
- ✅ Resetowanie haseł użytkowników

## 🔧 Konfiguracja środowiska

### Wymagane zmienne środowiskowe:

```bash
# OpenAI API
OPENAI_API_KEY=your-openai-api-key-here
OPENAI_MODEL=text-embedding-3-small

# Baza danych (w config.env)
DATABASE_URL=mysql://user:password@host:port/database
```

### Instalacja:

```bash
# Instalacja zależności
composer install

# Uruchomienie w Docker
./bin/run.sh ./bin/console doctrine:migrations:migrate
./bin/run.sh ./bin/console doctrine:fixtures:load
./bin/run.sh ./bin/console app:seed:tags

# Uruchomienie serwera
./bin/run.sh symfony serve
```

## 📊 Architektura

### Główne komponenty:
- **RecommendationService** - Logika biznesowa rekomendacji
- **OpenAIEmbeddingClient** - Klient API OpenAI do embeddingów
- **TextNormalizationService** - Normalizacja tekstu użytkowników
- **TagService** - Zarządzanie tagami

### Baza danych:
- **users** - Użytkownicy systemu
- **recommendations** - Rekomendacje użytkowników
- **recommendations_embeddings** - Embeddingi OpenAI dla rekomendacji
- **ebooks** - Katalog książek
- **ebooks_embeddings** - Embeddingi książek dla wyszukiwania
- **tags** - Tagi kategorii książek

## 🔄 Proces rekomendacji

1. Użytkownik wprowadza opis książki (30-500 znaków)
2. Tekst jest normalizowany i tworzony hash SHA256
3. Jeśli embedding nie istnieje, pobierany jest z OpenAI API
4. Embedding jest cachowany w bazie danych
5. System wyszukuje podobne książki używając cosine similarity

## 🧪 Testowanie

```bash
# Wszystkie testy
./bin/run.sh ./bin/phpunit

# Testy konkretnego modułu
./bin/run.sh ./bin/phpunit --filter OpenAIEmbeddingClientTest

# Pokrycie kodu
./bin/run.sh ./bin/phpunit --coverage-html=var/coverage
```

## 📋 Status projektu

- ✅ US-001: Rejestracja
- ✅ US-002: Logowanie
- ✅ US-003: Reset hasła
- ✅ US-004: Wylogowanie
- ✅ US-005: Wprowadzanie opisu książki + OpenAI embeddingi
- ⏳ US-006: Wyświetlanie rekomendacji
- ⏳ US-007: Usuwanie rekomendacji
- ✅ US-008: Batch processing embeddingów książek

## 🤝 Przyczynianie się

1. Fork projektu
2. Utwórz branch dla swojej funkcji (`git checkout -b feature/AmazingFeature`)
3. Commituj zmiany (`git commit -m 'Add some AmazingFeature'`)
4. Push do branch (`git push origin feature/AmazingFeature`)
5. Otwórz Pull Request
