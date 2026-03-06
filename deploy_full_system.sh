#!/bin/bash

# Full System Deployment Script for AI Application with ML Microservice

echo "AI Application - Full System Deployment"
echo "========================================"

display_help() {
    echo "Usage: $0 [option]"
    echo ""
    echo "Options:"
    echo "  build           Build all Docker images"
    echo "  up             Start full system (development)"
    echo "  up-prod         Start full system (production)"
    echo "  down           Stop all services"
    echo "  restart         Restart all services"
    echo "  logs            Show all service logs"
    echo "  ml-logs         Show only ML service logs"
    echo "  app-logs        Show only Laravel app logs"
    echo "  test            Run all tests"
    echo "  clean           Clean up all resources"
    echo "  status          Show service status"
    echo "  help            Display this help message"
}

build_images() {
    echo "Building Docker images..."
    
    # Build Laravel app
    echo "Building Laravel application..."
    docker build -t laravel-app:latest .
    if [ $? -ne 0 ]; then
        echo "✗ Laravel build failed"
        exit 1
    fi
    
    # Build ML service
    echo "Building ML microservice..."
    cd ml_service || exit 1
    docker build -t ml-microservice:latest .
    if [ $? -ne 0 ]; then
        echo "✗ ML service build failed"
        exit 1
    fi
    cd ..
    
    echo "✓ All images built successfully"
}

start_dev() {
    echo "Starting full system in development mode..."
    
    # Check if images exist, build if not
    if ! docker images | grep -q "laravel-app"; then
        build_images
    fi
    
    docker-compose up -d
    
    # Wait for services to start
    echo "Waiting for services to initialize..."
    sleep 10
    
    # Check service health
    check_status
    
    echo "✓ Full system started in development mode"
    echo ""
    echo "Access the application at: http://localhost:8080"
    echo "ML Service at: http://localhost:8001"
    echo "Check ML health: curl http://localhost:8001/"
}

start_prod() {
    echo "Starting full system in production mode..."
    
    # Always build fresh images for production
    build_images
    
    docker-compose -f docker-compose.prod.yml up -d --build
    
    # Wait for services to start
    echo "Waiting for services to initialize..."
    sleep 15
    
    # Check service health
    check_status
    
    echo "✓ Full system started in production mode"
    echo ""
    echo "Access the application at: http://localhost"
    echo "ML Service at: http://localhost:8001"
}

stop_services() {
    echo "Stopping all services..."
    docker-compose down
    docker-compose -f docker-compose.prod.yml down
    echo "✓ All services stopped"
}

restart_services() {
    stop_services
    sleep 3
    start_dev
}

show_all_logs() {
    echo "Showing all service logs..."
    docker-compose logs -f
}

show_ml_logs() {
    echo "Showing ML service logs..."
    docker-compose logs -f ml-service
}

show_app_logs() {
    echo "Showing Laravel app logs..."
    docker-compose logs -f app
}

run_tests() {
    echo "Running all tests..."
    
    # Check if services are running
    if ! docker-compose ps | grep -q "Up"; then
        echo "Starting services for testing..."
        docker-compose up -d
        sleep 8
    fi
    
    # Run ML service tests
    echo "Running ML microservice tests..."
    cd ml_service || exit 1
    python test_service.py
    if [ $? -ne 0 ]; then
        echo "✗ ML service tests failed"
        exit 1
    fi
    cd ..
    
    # Run Laravel integration tests
    echo "Running Laravel integration tests..."
    docker exec -it laravel_app php /var/www/html/ml_service/test_laravel_integration.php
    if [ $? -ne 0 ]; then
        echo "✗ Laravel integration tests failed"
        exit 1
    fi
    
    echo "✓ All tests passed successfully"
}

check_status() {
    echo "Checking service status..."
    echo ""
    
    # Check Laravel app
    if docker-compose ps app | grep -q "Up"; then
        echo "✓ Laravel application: Running"
    else
        echo "✗ Laravel application: Not running"
    fi
    
    # Check ML service
    if docker-compose ps ml-service | grep -q "Up"; then
        echo "✓ ML microservice: Running"
        
        # Check ML health
        if curl -s http://localhost:8001/ > /dev/null; then
            echo "✓ ML microservice: Healthy"
        else
            echo "⚠ ML microservice: Unhealthy"
        fi
    else
        echo "✗ ML microservice: Not running"
    fi
    
    # Check database
    if docker-compose ps db | grep -q "Up"; then
        echo "✓ Database: Running"
    else
        echo "✗ Database: Not running"
    fi
    
    # Check nginx
    if docker-compose ps nginx | grep -q "Up"; then
        echo "✓ Web server: Running"
    else
        echo "✗ Web server: Not running"
    fi
    
    echo ""
}

clean_up() {
    echo "Cleaning up all resources..."
    stop_services
    
    # Remove docker images
    docker rmi laravel-app:latest || true
    docker rmi ml-microservice:latest || true
    
    # Prune system
    docker system prune -f
    
    echo "✓ Cleanup completed"
}

# Main script
case "$1" in
    build)
        build_images
        ;;
    up)
        build_images
        start_dev
        ;;
    up-prod)
        start_prod
        ;;
    down)
        stop_services
        ;;
    restart)
        restart_services
        ;;
    logs)
        show_all_logs
        ;;
    ml-logs)
        show_ml_logs
        ;;
    app-logs)
        show_app_logs
        ;;
    test)
        run_tests
        ;;
    status)
        check_status
        ;;
    clean)
        clean_up
        ;;
    help|--help|-h|"")
        display_help
        ;;
    *)
        echo "Unknown option: $1"
        display_help
        exit 1
        ;;
esac