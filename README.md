# System Rekomendacji Książek

Aplikacja rekomendacji książek oparta na sztucznej inteligencji, wykorzystująca embeddings OpenAI do dopasowania preferencji użytkowników.

## 🚀 Technologie

- **PHP 8.4** - Język backend
- **Symfony 8.0** - Framework PHP
- **MySQL 8.4** - Relacyjna baza danych
- **Qdrant** - Baza wektorowa dla szybkiego wyszukiwania podobieństwa
- **HTMX** - Dynamiczne interfejsy bez JavaScript
- **OpenAI API** - Embeddings tekstowe (text-embedding-3-small)

## ✨ Funkcjonalności

### Dla użytkowników:
- ✅ Rejestracja i logowanie z bezpieczeństwem (throttling, rate limiting)
- ✅ Tworzenie rekomendacji książek z opisem (30-500 znaków)
- ✅ Automatyczne generowanie embeddingów przez OpenAI API
- ✅ Wybór tagów z inteligentnym wyszukiwaniem
- ✅ Wyświetlanie rekomendacji książek

### Dla administratorów:
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

# Uruchom z Docker
./bin/run.sh ./bin/console doctrine:migrations:migrate
./bin/run.sh ./bin/console doctrine:fixtures:load
./bin/run.sh ./bin/console app:seed:tags

# Uruchom serwer
./bin/run.sh symfony serve
```

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
- **ebooks** - Katalog książek z metadanymi
- **ebooks_embeddings** - Kopia embeddingów książek (synchronizacja z Qdrant)
- **tags** - Tagi kategorii książek

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
./bin/run.sh ./bin/phpunit --coverage-html=var/coverage
```

### Migracja danych:
```bash
# Migracja embeddingów książek do Qdrant
./bin/run.sh ./bin/console app:migrate:ebook-embeddings-to-qdrant

# Sprawdź statystyki kolekcji w Qdrant
./bin/run.sh ./bin/console app:migrate:ebook-embeddings-to-qdrant --stats-only
```

## 🤝 Współtworzenie

1. Zrób fork projektu
2. Utwórz gałąź dla swojej funkcjonalności (`git checkout -b feature/NiesamowitaFunkcjonalnosc`)
3. Zacommituj swoje zmiany (`git commit -m 'Dodaj jakąś NiesamowitąFunkcjonalność'`)
4. Wypchnij do swojej gałęzi (`git push origin feature/NiesamowitaFunkcjonalnosc`)
5. Otwórz Pull Request
