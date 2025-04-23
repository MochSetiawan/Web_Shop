<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';

// Pastikan request berisi query
if (isset($_GET['query']) && !empty($_GET['query'])) {
    $query = sanitize($_GET['query']);
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 5;
    
    // Lakukan pencarian di database
    $stmt = $conn->prepare("
        SELECT p.id, p.name, p.slug, p.price, p.sale_price, p.image, c.name as category_name
        FROM products p
        LEFT JOIN categories c ON p.category_id = c.id
        WHERE (p.name LIKE ? OR p.description LIKE ? OR c.name LIKE ?)
        AND p.status = 'active'
        LIMIT ?
    ");
    
    $search_term = "%$query%";
    $stmt->bind_param("sssi", $search_term, $search_term, $search_term, $limit);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $products = [];
    
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            // Tambahkan URL gambar dan link produk
            $image_url = !empty($row['image']) 
                ? SITE_URL . '/assets/img/products/' . $row['image'] 
                : SITE_URL . '/assets/img/no-image.jpg';
                
            $product_url = SITE_URL . '/product/' . $row['slug'];
            
            // Format harga
            $price = $row['price'];
            $sale_price = $row['sale_price'];
            $price_html = formatPrice($price);
            
            if ($sale_price > 0 && $sale_price < $price) {
                $price_html = '<span class="text-danger">' . formatPrice($sale_price) . '</span> <small class="text-muted text-decoration-line-through">' . formatPrice($price) . '</small>';
            }
            
            $products[] = [
                'id' => $row['id'],
                'name' => $row['name'],
                'url' => $product_url,
                'image' => $image_url,
                'price_html' => $price_html,
                'category' => $row['category_name']
            ];
        }
    }
    
    // Kembalikan sebagai JSON
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'products' => $products,
        'count' => count($products),
        'query' => $query
    ]);
    exit;
}

// Jika tidak ada query atau terjadi error
header('Content-Type: application/json');
echo json_encode([
    'success' => false,
    'message' => 'Invalid search query'
]);
?>