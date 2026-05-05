<?php
session_start();
include 'db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Check if user is a seller
$is_seller = false;
$seller_check = $conn->query("SHOW COLUMNS FROM users LIKE 'is_seller'");
if ($seller_check->num_rows > 0) {
    $stmt = $conn->prepare("SELECT is_seller FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $user_seller = $result->fetch_assoc();
    $stmt->close();
    
    $is_seller = isset($user_seller['is_seller']) && $user_seller['is_seller'] == 1;
}

// Get user info
$stmt = $conn->prepare("SELECT first_name, last_name, email FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Get order stats
$stmt = $conn->prepare("
    SELECT COUNT(*) as total_orders, SUM(total_amount) as total_spent
    FROM orders WHERE user_id = ?
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stats = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Get recent orders
$stmt = $conn->prepare("
    SELECT id, order_date, total_amount, status FROM orders
    WHERE user_id = ? ORDER BY order_date DESC LIMIT 5
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$recent_orders = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - ReBuy</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="../css/header-footer.css">
    <style>
        .hero-banner {
            background: linear-gradient(135deg, #2d5016 0%, #4a7c2e 100%);
            color: white;
            padding: 80px 0;
            margin-bottom: 60px;
            position: relative;
            overflow: hidden;
        }
        .carousel-container {
            position: relative;
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 40px;
            height: 500px;
        }
        .carousel-wrapper {
            position: relative;
            height: 100%;
            overflow: hidden;
            border-radius: 15px;
        }
        .carousel-slides {
            display: flex;
            height: 100%;
            transition: transform 0.6s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .carousel-slide {
            min-width: 100%;
            display: flex;
            align-items: center;
            gap: 60px;
            height: 100%;
            opacity: 0;
            transform: translateX(100px);
            transition: all 0.6s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .carousel-slide.active {
            opacity: 1;
            transform: translateX(0);
        }
        .carousel-slide.prev {
            opacity: 0;
            transform: translateX(-100px);
        }
        .carousel-nav {
            position: absolute;
            top: 50%;
            transform: translateY(-50%);
            background: rgba(255,255,255,0.2);
            color: white;
            border: none;
            width: 50px;
            height: 50px;
            border-radius: 50%;
            cursor: pointer;
            font-size: 18px;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            z-index: 5;
            backdrop-filter: blur(10px);
        }
        .carousel-nav:hover {
            background: rgba(255,255,255,0.3);
            transform: translateY(-50%) scale(1.1);
        }
        .carousel-prev {
            left: 20px;
        }
        .carousel-next {
            right: 20px;
        }
        .carousel-indicators {
            position: absolute;
            bottom: 30px;
            left: 50%;
            transform: translateX(-50%);
            display: flex;
            gap: 12px;
            z-index: 5;
        }
        .indicator {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background: rgba(255,255,255,0.3);
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            backdrop-filter: blur(10px);
        }
        .indicator:hover {
            background: rgba(255,255,255,0.5);
            transform: scale(1.2);
        }
        .indicator.active {
            background: white;
            transform: scale(1.3);
        }
        .hero-text {
            flex: 1;
            padding: 40px;
        }
        .hero-text h1 {
            font-size: 48px;
            margin-bottom: 20px;
            font-weight: 700;
            opacity: 0;
            transform: translateY(30px);
            animation: slideInUp 0.8s cubic-bezier(0.4, 0, 0.2, 1) forwards;
        }
        .carousel-slide.active .hero-text h1 {
            animation-delay: 0.2s;
        }
        .hero-text p {
            font-size: 18px;
            margin-bottom: 30px;
            opacity: 0;
            transform: translateY(30px);
            animation: slideInUp 0.8s cubic-bezier(0.4, 0, 0.2, 1) forwards;
        }
        .carousel-slide.active .hero-text p {
            animation-delay: 0.4s;
        }
        .hero-image {
            flex: 1;
            text-align: center;
            padding: 40px;
        }
        .hero-image img {
            max-width: 100%;
            height: 400px;
            object-fit: cover;
            border-radius: 15px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.3);
            opacity: 0;
            transform: scale(0.9) translateY(30px);
            animation: slideInUp 0.8s cubic-bezier(0.4, 0, 0.2, 1) forwards;
        }
        .carousel-slide.active .hero-image img {
            animation-delay: 0.6s;
        }
        @keyframes slideInUp {
            to {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }
        .btn-shop {
            display: inline-block;
            background: white;
            color: #2d5016;
            padding: 15px 40px;
            border-radius: 30px;
            text-decoration: none;
            font-weight: 600;
            font-size: 16px;
            transition: all 0.3s ease;
        }
        .btn-shop:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
        }
        .section-title {
            text-align: center;
            font-size: 32px;
            margin-bottom: 40px;
            color: #333;
            font-weight: 600;
        }
        .categories-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 30px;
            margin-bottom: 60px;
            max-width: 1200px;
            margin-left: auto;
            margin-right: auto;
            padding: 0 40px;
        }
        .category-card {
            background: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            transition: transform 0.3s ease;
            cursor: pointer;
        }
        .category-card:hover {
            transform: translateY(-5px);
        }
        .category-card img {
            width: 100%;
            height: 200px;
            object-fit: cover;
        }
        .category-card h3 {
            padding: 15px;
            margin: 0;
            text-align: center;
            color: #333;
            font-size: 16px;
        }
        .rooms-section {
            margin-bottom: 60px;
            padding: 0 40px;
        }
        .rooms-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 30px;
            max-width: 1200px;
            margin: 0 auto;
        }
        .room-card {
            background: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        .room-card img {
            width: 100%;
            height: 250px;
            object-fit: cover;
        }
        .room-content {
            padding: 20px;
            text-align: center;
        }
        .room-content h3 {
            margin: 0 0 10px 0;
            color: #333;
            font-size: 20px;
        }
        .room-content p {
            color: #666;
            margin-bottom: 20px;
        }
        .hot-products {
            background: #f8f9fa;
            padding: 60px 0;
        }
        .products-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 30px;
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 40px;
        }
        .product-card {
            background: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            position: relative;
        }
        .product-card img {
            width: 100%;
            height: 200px;
            object-fit: cover;
        }
        .discount-badge {
            position: absolute;
            top: 10px;
            right: 10px;
            background: #ff4444;
            color: white;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        .product-info {
            padding: 20px;
        }
        .product-info h4 {
            margin: 0 0 10px 0;
            color: #333;
            font-size: 16px;
        }
        .product-price {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 15px;
        }
        .current-price {
            font-size: 18px;
            font-weight: 600;
            color: #2d5016;
        }
        .original-price {
            font-size: 14px;
            color: #999;
            text-decoration: line-through;
        }
        .btn-add-cart {
            width: 100%;
            background: #2d5016;
            color: white;
            border: none;
            padding: 12px;
            border-radius: 5px;
            cursor: pointer;
            font-weight: 600;
            transition: background 0.3s ease;
        }
        .btn-add-cart:hover {
            background: #4a7c2e;
        }
    </style>
</head>
<body>
    <div class="page-wrapper">
        <?php include '_header.php'; ?>

        <?php if ($is_seller): ?>
        <!-- Seller Welcome Message -->
        <div style="background: linear-gradient(135deg, #2d5016 0%, #4a7c2e 100%); color: white; padding: 30px 40px; text-align: center; margin-bottom: 0;">
            <h1 style="font-size: 36px; margin-bottom: 10px; font-weight: 700;">
                <i class="fas fa-store" style="margin-right: 15px;"></i>Welcome Seller!
            </h1>
            <p style="font-size: 18px; opacity: 0.9; margin: 0;">Manage your store and track your sales performance</p>
            <div style="margin-top: 20px;">
                <a href="seller_dashboard.php" style="background: #f4c430; color: #333; padding: 12px 30px; border-radius: 6px; text-decoration: none; font-weight: 600; display: inline-block; transition: all 0.3s;">
                    <i class="fas fa-tachometer-alt" style="margin-right: 8px;"></i>Go to Seller Dashboard
                </a>
            </div>
        </div>
        <?php endif; ?>

        <!-- Hero Banner Carousel -->
        <section class="hero-banner">
            <div class="carousel-container">
                <div class="carousel-wrapper">
                    <div class="carousel-slides">
                        <!-- Slide 1 -->
                        <div class="carousel-slide active">
                            <div class="hero-text">
                                <h1>Spring Collection</h1>
                                <p>Refresh your home with our latest furniture collection. Modern designs for modern living.</p>
                                <a href="shop.php" class="btn-shop">Shop now</a>
                            </div>
                            <div class="hero-image">
                                <img src="https://images.unsplash.com/photo-1586023492125-27b2c045efd7?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&w=1000&q=80" alt="Modern Armchair">
                            </div>
                        </div>

                        <!-- Slide 2 -->
                        <div class="carousel-slide">
                            <div class="hero-text">
                                <h1>Summer Sale</h1>
                                <p>Up to 50% off on selected items. Limited time offer - don't miss out on amazing deals!</p>
                                <a href="shop.php" class="btn-shop">View Deals</a>
                            </div>
                            <div class="hero-image">
                                <img src="https://images.unsplash.com/photo-1555041469-a586c61ea9bc?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&w=1000&q=80" alt="Luxury Sofa">
                            </div>
                        </div>

                        <!-- Slide 3 -->
                        <div class="carousel-slide">
                            <div class="hero-text">
                                <h1>Modern Living</h1>
                                <p>Transform your space with contemporary furniture that combines style and functionality.</p>
                                <a href="shop.php" class="btn-shop">Explore</a>
                            </div>
                            <div class="hero-image">
                                <img src="https://images.unsplash.com/photo-1616486338812-3dadae4b4ace?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&w=1000&q=80" alt="Modern Lamp">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Navigation Buttons -->
                <button class="carousel-nav carousel-prev" onclick="changeSlide(-1)">
                    <i class="fas fa-chevron-left"></i>
                </button>
                <button class="carousel-nav carousel-next" onclick="changeSlide(1)">
                    <i class="fas fa-chevron-right"></i>
                </button>

                <!-- Indicators -->
                <div class="carousel-indicators">
                    <span class="indicator active" onclick="goToSlide(0)"></span>
                    <span class="indicator" onclick="goToSlide(1)"></span>
                    <span class="indicator" onclick="goToSlide(2)"></span>
                </div>
            </div>
        </section>

        <!-- Shop by Categories -->
        <section>
            <h2 class="section-title">Shop by categories</h2>
            <div class="categories-grid">
                <div class="category-card" onclick="window.location.href='shop.php'">
                    <img src="https://images.unsplash.com/photo-1586023492125-27b2c045efd7?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&w=400&q=80" alt="Dining Chair">
                    <h3>Dining Chair</h3>
                </div>
                <div class="category-card" onclick="window.location.href='shop.php'">
                    <img src="https://images.unsplash.com/photo-1555041469-a586c61ea9bc?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&w=400&q=80" alt="Sofa">
                    <h3>Sofa</h3>
                </div>
                <div class="category-card" onclick="window.location.href='shop.php'">
                    <img src="https://images.unsplash.com/photo-1506439773649-6e0eb8cfb237?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&w=400&q=80" alt="Table">
                    <h3>Table</h3>
                </div>
                <div class="category-card" onclick="window.location.href='shop.php'">
                    <img src="https://images.unsplash.com/photo-1616486338812-3dadae4b4ace?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&w=400&q=80" alt="Lamp">
                    <h3>Lamp</h3>
                </div>
            </div>
        </section>

        <!-- Living Room & Dining Room -->
        <section class="rooms-section">
            <div class="rooms-grid">
                <div class="room-card">
                    <img src="https://images.unsplash.com/photo-1616486338812-3dadae4b4ace?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&w=600&q=80" alt="Living Room">
                    <div class="room-content">
                        <h3>Living Room</h3>
                        <p>Transform your living space with our premium furniture collection</p>
                        <a href="shop.php" class="btn-shop">Shop now</a>
                    </div>
                </div>
                <div class="room-card">
                    <img src="https://images.unsplash.com/photo-1586023492125-27b2c045efd7?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&w=600&q=80" alt="Dining Room">
                    <div class="room-content">
                        <h3>Dining Room</h3>
                        <p>Create memorable moments with our elegant dining furniture</p>
                        <a href="shop.php" class="btn-shop">Shop now</a>
                    </div>
                </div>
            </div>
        </section>

        <!-- Hot Products -->
        <section class="hot-products">
            <h2 class="section-title">Hot Products</h2>
        </section>

         <!-- Footer -->
    <footer>
        <div class="footer-container">
            <div class="footer-content">
                <div class="footer-section">
                    <div class="footer-logo">
                        <i class="fas fa-shopping-bag"></i>
                        <span>ReBuy</span>
                    </div>
                    <p class="footer-text">ReBuy lets you buy quality second-hand items for less, saving money while supporting a more sustainable lifestyle.</p>
                    <div class="social-links">
                        <a href="#"><i class="fab fa-twitter"></i></a>
                        <a href="#"><i class="fab fa-instagram"></i></a>
                        <a href="#"><i class="fab fa-pinterest"></i></a>
                    </div>
                </div>

                <div class="footer-section">
                    <h3>Company</h3>
                    <ul>
                        <li><a href="about_us.php">About Us</a></li>
                        <li><a href="#">Contact Us</a></li>
                    </ul>
                </div>

                <div class="footer-section">
                    <h3>Customer Services</h3>
                    <ul>
                        <li><a href="settings.php">My Account</a></li>
                        <li><a href="#">Track Your Order</a></li>
                        <li><a href="#">Returns</a></li>
                        <li><a href="#">FAQ</a></li>
                    </ul>
                </div>

                <div class="footer-section">
                    <h3>Our Information</h3>
                    <ul>
                        <li><a href="#">Privacy Policy</a></li>
                        <li><a href="#">Terms & Condition</a></li>
                        <li><a href="#">Return Policy</a></li>
                        <li><a href="#">Shipping Info</a></li>
                    </ul>
                </div>

                <div class="footer-section">
                    <h3>Contact Info</h3>
                    <p class="footer-text"><i class="fas fa-phone"></i> +639813446215</p>
                    <p class="footer-text"><i class="fa-solid fa-envelope"></i> rebuy@gmail.com</p>
                    <p class="footer-text"><i class="fa-solid fa-location-dot"></i> T. Curato St. Cabadbaran City Agusan Del Norte, Philippines, 8600</p>
                </div>
            </div>

            <div class="footer-bottom">
                <p>&copy; Copyright @ 2026 <strong>ReBuy</strong>. All Rights Reserved.</p>
            </div>
        </div>
    </footer>
    </div>

    <script>
        // User dropdown menu
        document.querySelector('.icon-btn').addEventListener('click', function() {
            document.querySelector('.user-dropdown').classList.toggle('active');
        });

        // Close dropdown when clicking outside
        document.addEventListener('click', function(event) {
            const userMenu = document.querySelector('.user-menu');
            if (!userMenu.contains(event.target)) {
                document.querySelector('.user-dropdown').classList.remove('active');
            }
        });

        // Add to cart functionality
        document.querySelectorAll('.btn-add-cart').forEach(button => {
            button.addEventListener('click', function() {
                const productName = this.closest('.product-info').querySelector('h4').textContent;
                alert(`${productName} added to cart!`);
            });
        });

        // Carousel functionality
        let currentSlide = 0;
        const slides = document.querySelectorAll('.carousel-slide');
        const indicators = document.querySelectorAll('.indicator');
        const totalSlides = slides.length;
        let isTransitioning = false;

        function showSlide(index) {
            if (isTransitioning) return;
            isTransitioning = true;
            
            // Remove all active classes
            slides.forEach(slide => slide.classList.remove('active', 'prev'));
            indicators.forEach(indicator => indicator.classList.remove('active'));
            
            // Add prev class to current slide for smooth exit
            slides[currentSlide].classList.add('prev');
            
            // Update current slide
            currentSlide = index;
            
            // Add active class to new slide
            slides[currentSlide].classList.add('active');
            indicators[currentSlide].classList.add('active');
            
            // Reset animation states
            const activeSlideElements = slides[currentSlide].querySelectorAll('.hero-text h1, .hero-text p, .hero-image img');
            activeSlideElements.forEach(el => {
                el.style.animation = 'none';
                el.offsetHeight; // Trigger reflow
                el.style.animation = null;
            });
            
            setTimeout(() => {
                isTransitioning = false;
            }, 600);
        }

        function changeSlide(direction) {
            let newSlide = currentSlide + direction;
            
            if (newSlide >= totalSlides) {
                newSlide = 0;
            } else if (newSlide < 0) {
                newSlide = totalSlides - 1;
            }
            
            showSlide(newSlide);
        }

        function goToSlide(index) {
            if (index === currentSlide) return;
            showSlide(index);
        }

        // Auto-rotate carousel
        let autoRotateInterval;
        
        function startAutoRotate() {
            autoRotateInterval = setInterval(() => {
                changeSlide(1);
            }, 5000);
        }
        
        function stopAutoRotate() {
            clearInterval(autoRotateInterval);
        }

        // Start auto-rotation
        startAutoRotate();

        // Pause auto-rotation on hover
        const carouselContainer = document.querySelector('.carousel-container');
        
        carouselContainer.addEventListener('mouseenter', () => {
            stopAutoRotate();
        });

        carouselContainer.addEventListener('mouseleave', () => {
            startAutoRotate();
        });

        // Keyboard navigation
        document.addEventListener('keydown', (e) => {
            if (e.key === 'ArrowLeft') {
                changeSlide(-1);
                stopAutoRotate();
                startAutoRotate();
            } else if (e.key === 'ArrowRight') {
                changeSlide(1);
                stopAutoRotate();
                startAutoRotate();
            }
        });

        // Touch support for mobile
        let touchStartX = 0;
        let touchEndX = 0;
        
        carouselContainer.addEventListener('touchstart', (e) => {
            touchStartX = e.changedTouches[0].screenX;
        });
        
        carouselContainer.addEventListener('touchend', (e) => {
            touchEndX = e.changedTouches[0].screenX;
            handleSwipe();
        });
        
        function handleSwipe() {
            const swipeThreshold = 50;
            const diff = touchStartX - touchEndX;
            
            if (Math.abs(diff) > swipeThreshold) {
                if (diff > 0) {
                    changeSlide(1); // Swipe left, go to next slide
                } else {
                    changeSlide(-1); // Swipe right, go to previous slide
                }
                stopAutoRotate();
                startAutoRotate();
            }
        }
    </script>
</body>
</html>
