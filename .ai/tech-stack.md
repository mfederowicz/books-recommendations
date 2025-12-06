Frontend:
- htmx 
- tailwind
- basecoatui

Backend:
- baza mysql 8.x
- php + biblioteki composerowe dla openai
- symfony 8.x
- qdrant - vector similarity search engine and vector database

AI:
- wykorzystanie modelu text-embedding-3-small - do pozyskiwania wektorów jako podstawa do mechanizmu rekomendacji

Testowanie:
- PHPUnit 10.5 do testów jednostkowych i integracyjnych
- Doctrine DataFixtures do zarządzania danymi testowymi
- PCOV do analizy pokrycia kodu testami
- Prophecy/Mockery do mockowania zależności

CI/CD i Hosting:
- Github Actions do tworzenia pipeline’ów CI/CD
- Ovh do hostowania aplikacji na vps
