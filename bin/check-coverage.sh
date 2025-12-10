#!/bin/bash

# Script to check code coverage locally
# Usage: ./bin/check-coverage.sh

set -e

echo "🔍 Checking code coverage locally..."
echo

# Create coverage directory if it doesn't exist
mkdir -p var/coverage

# Run PHPUnit with coverage
echo "📊 Running tests with coverage analysis..."
if command -v docker &> /dev/null && [ -f bin/docker-compose.sh ]; then
    ENABLE_PCOV=1 ./bin/run.sh vendor/bin/phpunit --stderr --colors=never --coverage-clover=var/coverage/clover.xml --coverage-html=var/coverage-html
else
    ENABLE_PCOV=1 vendor/bin/phpunit --stderr --colors=never --coverage-clover=var/coverage/clover.xml --coverage-html=var/coverage-html
fi

echo
echo "📈 Coverage Report:"
echo "=================="

# Check if clover file exists
if [ -f "var/coverage/clover.xml" ]; then
    # Extract coverage percentage from clover XML
    # This is a simple extraction - you might want to use a more sophisticated tool
    COVERAGE=$(grep -oP '(?<=<coverage generated="\d+" statements="\d+" coveredstatements="\d+" line-rate=")[^"]*' var/coverage/clover.xml | head -1)

    if [ -n "$COVERAGE" ]; then
        PERCENTAGE=$(echo "scale=2; $COVERAGE * 100" | bc 2>/dev/null || echo "N/A")
        echo "Code Coverage: ${PERCENTAGE}%"
    else
        # Extract from project metrics (the one with files attribute)
        METRICS_LINE=$(grep 'files=' var/coverage/clover.xml)
        TOTAL_STATEMENTS=$(echo "$METRICS_LINE" | grep -oP 'statements="\K[^"]*')
        COVERED_STATEMENTS=$(echo "$METRICS_LINE" | grep -oP 'coveredstatements="\K[^"]*')

        if [ -n "$TOTAL_STATEMENTS" ] && [ -n "$COVERED_STATEMENTS" ]; then
            PERCENTAGE=$(echo "scale=2; $COVERED_STATEMENTS * 100 / $TOTAL_STATEMENTS" | bc 2>/dev/null || echo "N/A")
            echo "Code Coverage: ${PERCENTAGE}% (${COVERED_STATEMENTS}/${TOTAL_STATEMENTS} statements)"
        else
            echo "Could not extract coverage data from clover file"
        fi
    fi
else
    echo "Coverage file not found: var/coverage/clover.xml"
fi

echo
echo "📁 Coverage reports generated:"
echo "  - HTML Report: var/coverage-html/index.html"
echo "  - Clover XML: var/coverage/clover.xml"

echo
echo "💡 Tip: Open var/coverage-html/index.html in your browser to see detailed coverage report"
