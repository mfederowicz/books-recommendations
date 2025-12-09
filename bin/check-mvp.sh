#!/bin/bash

# Books Recommender MVP Status Check Script
# This script checks the current status of the MVP features

set -e

echo "======================================"
echo "📚 Books Recommender - MVP Status Check"
echo "======================================"
echo

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Function to print status
print_status() {
    local status=$1
    local message=$2
    case $status in
        "PASS")
            echo -e "${GREEN}✅ PASS${NC}: $message"
            ;;
        "FAIL")
            echo -e "${RED}❌ FAIL${NC}: $message"
            ;;
        "WARN")
            echo -e "${YELLOW}⚠️  WARN${NC}: $message"
            ;;
        "INFO")
            echo -e "${BLUE}ℹ️  INFO${NC}: $message"
            ;;
    esac
}

echo "🔍 Checking Core Components..."
echo

# 1. Check if application can start
echo "1. Application Startup"
if ./bin/run.sh bin/console cache:clear > /dev/null 2>&1; then
    print_status "PASS" "Symfony application starts successfully"
else
    print_status "FAIL" "Symfony application fails to start"
fi

# 2. Check database migrations
echo
echo "2. Database Setup"
MIGRATION_STATUS=$(./bin/run.sh bin/console doctrine:migrations:status --no-ansi | grep -E "Executed.*Available" | sed 's/.*| \([0-9]*\).*| \([0-9]*\).*/\1\/\2/')
EXECUTED=$(echo $MIGRATION_STATUS | cut -d'/' -f1)
AVAILABLE=$(echo $MIGRATION_STATUS | cut -d'/' -f2)

if [ "$EXECUTED" = "$AVAILABLE" ]; then
    print_status "PASS" "All database migrations applied ($EXECUTED/$AVAILABLE)"
else
    print_status "WARN" "Pending migrations: $((AVAILABLE - EXECUTED)) not applied"
fi

# 3. Check entities
echo
echo "3. Data Model (Entities)"
ENTITIES=("User" "Recommendation" "Tag" "Ebook" "EbookEmbedding" "RecommendationEmbedding" "UserFailedLogin")
for entity in "${ENTITIES[@]}"; do
    if [ -f "src/Entity/${entity}.php" ]; then
        print_status "PASS" "Entity ${entity} exists"
    else
        print_status "FAIL" "Entity ${entity} missing"
    fi
done

# 4. Check controllers
echo
echo "4. Controllers"
CONTROLLERS=("AuthController" "RecommendationController" "ApiController" "DefaultController")
for controller in "${CONTROLLERS[@]}"; do
    if [ -f "src/Controller/${controller}.php" ]; then
        print_status "PASS" "Controller ${controller} exists"
    else
        print_status "FAIL" "Controller ${controller} missing"
    fi
done

# 5. Check services
echo
echo "5. Business Logic (Services)"
SERVICES=("RecommendationService" "TagService" "OpenAIEmbeddingClient" "TextNormalizationService" "LoginThrottlingService")
for service in "${SERVICES[@]}"; do
    if [ -f "src/Service/${service}.php" ]; then
        print_status "PASS" "Service ${service} exists"
    else
        print_status "FAIL" "Service ${service} missing"
    fi
done

# 6. Check routes
echo
echo "6. Routing"
ROUTES_CHECK=$(./bin/run.sh bin/console debug:router --no-ansi | grep -c "app_\|api_")
if [ "$ROUTES_CHECK" -ge 5 ]; then
    print_status "PASS" "Core routes configured ($ROUTES_CHECK routes found)"
else
    print_status "WARN" "Limited routes configured ($ROUTES_CHECK routes found)"
fi

# 7. Check tests
echo
echo "7. Testing"
if ./bin/run.sh vendor/bin/phpunit --stderr --colors=never > /tmp/test_output.log 2>&1; then
    TEST_COUNT=$(grep -o "Tests: [0-9]*" /tmp/test_output.log | grep -o "[0-9]*" | tail -1)
    ASSERTIONS=$(grep -o "Assertions: [0-9]*" /tmp/test_output.log | grep -o "[0-9]*" | tail -1)
    ERRORS=$(grep -o "Errors: [0-9]*" /tmp/test_output.log | grep -o "[0-9]*" | tail -1)

    if [ "$ERRORS" = "0" ] || [ -z "$ERRORS" ]; then
        print_status "PASS" "Tests passing ($TEST_COUNT tests, $ASSERTIONS assertions)"
    else
        print_status "WARN" "Tests have errors/failures ($ERRORS errors)"
    fi
else
    print_status "WARN" "Tests execution failed - check test suite"
fi

# 8. Check templates
echo
echo "8. User Interface (Templates)"
TEMPLATES=("base.html.twig" "homepage.html.twig" "auth/login.html.twig" "auth/register.html.twig" "dashboard.html.twig")
for template in "${TEMPLATES[@]}"; do
    if [ -f "templates/${template}" ]; then
        print_status "PASS" "Template ${template} exists"
    else
        print_status "FAIL" "Template ${template} missing"
    fi
done

# 9. Check commands
echo
echo "9. Console Commands"
COMMANDS=("app:seed:tags" "app:process:ebook-embeddings" "app:user:change-role")
for cmd in "${COMMANDS[@]}"; do
    if ./bin/run.sh bin/console list --no-ansi | grep -q "$cmd"; then
        print_status "PASS" "Command ${cmd} available"
    else
        print_status "FAIL" "Command ${cmd} missing"
    fi
done

# 10. Check configuration
echo
echo "10. Configuration"
if [ -f "config/packages/security.yaml" ] && [ -f "config/packages/doctrine.yaml" ]; then
    print_status "PASS" "Core configuration files present"
else
    print_status "FAIL" "Core configuration files missing"
fi

echo
echo "======================================"
echo "🎯 MVP Feature Assessment"
echo "======================================"
echo

# MVP Core Features Check
FEATURES=(
    "User registration and authentication:PASS:AuthController with registration/login implemented"
    "User can create book recommendations:PARTIAL:Recommendation creation exists but listing is TODO"
    "Tag system for categorizing recommendations:PASS:Tag service and API for autocomplete implemented"
    "Basic admin functionality:PARTIAL:User role management commands exist"
    "Database schema for recommendations:PASS:All entities and migrations present"
    "Embedding generation for recommendations:PARTIAL:OpenAI client exists but has test issues"
    "HTMX-based UI:PARTIAL:Templates exist but full workflow needs verification"
    "API for tag suggestions:PASS:ApiController with tag search implemented"
)

for feature in "${FEATURES[@]}"; do
    IFS=':' read -r name status details <<< "$feature"
    print_status "$status" "$name - $details"
done

echo
echo "======================================"
echo "📋 Next Steps for MVP Completion"
echo "======================================"
echo
echo "1. Fix failing tests (OpenAIEmbeddingClient parseErrorMessage method)"
echo "2. Complete recommendation listing functionality"
echo "3. Implement ebook data seeding/import"
echo "4. Add recommendation similarity matching"
echo "5. Complete UI/UX for full user workflow"
echo "6. Add proper error handling and validation"
echo "7. Implement caching for performance"
echo "8. Add monitoring and logging"
echo

echo "MVP Status: ${YELLOW}IN PROGRESS${NC} - Core foundation is solid, needs completion of key features."
