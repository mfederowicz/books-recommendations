# Books Recommendations System

An AI-powered book recommendation application using OpenAI embeddings to match user preferences.

## 🚀 Technologies

- **PHP 8.4** - Backend language
- **Symfony 8.0** - PHP framework
- **MySQL 8.4** - Relational database
- **Qdrant** - Vector database for fast similarity search
- **HTMX** - Dynamic interfaces without JavaScript
- **OpenAI API** - Text embeddings (text-embedding-3-small)

## ✨ Features

### For users:
- ✅ Registration and login with security (throttling, rate limiting)
- ✅ Creating book recommendations with description (30-500 characters)
- ✅ Automatic embedding generation via OpenAI API
- ✅ Tag selection with intelligent search
- ✅ Displaying book recommendations

### For administrators:
- ✅ Command for batch processing book embeddings: `app:process:ebook-embeddings`
- ✅ Migration of book embeddings to Qdrant: `app:migrate:ebook-embeddings-to-qdrant`
- ✅ Testing Qdrant functionality: `app:test:qdrant`
- ✅ Testing OpenAI embeddings: `app:test:embedding`
- ✅ User management
- ✅ Password reset for users

## 🔧 Environment Setup

### Required environment variables:

```bash
# OpenAI API
OPENAI_API_KEY=your-openai-api-key-here
OPENAI_MODEL=text-embedding-3-small

# Qdrant Vector Database
QDRANT_HOST=localhost
QDRANT_PORT=6333

# Database (in config.env)
DATABASE_URL=mysql://user:password@host:port/database
```

### Installation:

```bash
# Install dependencies
composer install

# Run with Docker
./bin/run.sh ./bin/console doctrine:migrations:migrate
./bin/run.sh ./bin/console doctrine:fixtures:load
./bin/run.sh ./bin/console app:seed:tags

# Start server
./bin/run.sh symfony serve
```

## 📊 Architecture

### Main components:
- **RecommendationService** - Business logic for recommendations and similar book search
- **OpenAIEmbeddingClient** - OpenAI API client for generating embeddings
- **EbookEmbeddingService** - Management of book embeddings in Qdrant
- **QdrantClient** - Qdrant vector database client
- **TextNormalizationService** - User text normalization
- **TagService** - Book tag management

### Database:

#### MySQL (relational data):
- **users** - System users
- **recommendations** - User recommendations
- **recommendations_embeddings** - OpenAI embeddings for user recommendations
- **ebooks** - Book catalog with metadata
- **ebooks_embeddings** - Copy of book embeddings (synchronization with Qdrant)
- **tags** - Book category tags

#### Qdrant (vector database):
- **ebooks** - Collection of book embeddings for fast vector search
- **recommendations** - User embeddings (MySQL only for optimization)

## 🔄 Recommendation process

### Tworzenie rekomendacji:
1. Użytkownik wprowadza opis książki (30-500 znaków)
2. Tekst jest normalizowany i tworzony hash SHA256
3. Jeśli embedding nie istnieje, pobierany jest z OpenAI API
4. Embedding jest cachowany w MySQL (`recommendations_embeddings`)
5. Rekomendacja jest zapisywana z wybranymi tagami

### Searching for similar books:
1. An embedding is generated based on the user's recommendation description.
2. The user's embedding is used as a query to search in Qdrant.
3. Qdrant returns books with the highest cosine similarity.
4. The results are filtered and returned to the user.

### Optimization architecture:
- **User embeddings**: Stored only in MySQL (resource efficient)
- **Book embeddings**: Synchronized between MySQL and Qdrant (fast lookup)
- **Search**: Query embedding → Qdrant → cosine similarity → results

## 🧪 Tests

```bash
# All tests
./bin/run.sh ./bin/phpunit

# Tests of selected module
./bin/run.sh ./bin/phpunit --filter OpenAIEmbeddingClientTest
./bin/run.sh ./bin/phpunit --filter RecommendationServiceTest

# Integration with external services
./bin/run.sh ./bin/console app:test:embedding "test text"
./bin/run.sh ./bin/console app:test:qdrant --create-test-data

# Code coverage
./bin/run.sh ./bin/phpunit --coverage-html=var/coverage
```

### Data migration:
```bash
# Migrate books embeddings to Qdrant
./bin/run.sh ./bin/console app:migrate:ebook-embeddings-to-qdrant

# Check collections stats in Qdrant
./bin/run.sh ./bin/console app:migrate:ebook-embeddings-to-qdrant --stats-only
```

## 🤝 Contributing 

1. Fork the project
2. Create a branch for your feature (`git checkout -b feature/AmazingFeature`)
3. Commit your changes (`git commit -m 'Add some AmazingFeature'`)
4. Push to your branch (`git push origin feature/AmazingFeature`)
5. Open a Pull Request
