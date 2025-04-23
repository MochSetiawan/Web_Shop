<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';

$pageTitle = 'Contact Us';

// Process contact form submission
$formSubmitted = false;
$formSuccess = false;
$formError = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['contact_submit'])) {
    $formSubmitted = true;
    
    // Get form fields
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $subject = trim($_POST['subject']);
    $message = trim($_POST['message']);
    
    // Validate form
    if (empty($name) || empty($email) || empty($subject) || empty($message)) {
        $formError = 'Semua kolom harus diisi';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $formError = 'Email tidak valid';
    } else {
        // Save to database (create a messages table if it doesn't exist)
        $sql = "CREATE TABLE IF NOT EXISTS contact_messages (
            id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100) NOT NULL,
            email VARCHAR(100) NOT NULL,
            subject VARCHAR(200) NOT NULL,
            message TEXT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )";
        $conn->query($sql);
        
        // Insert message
        $name = $conn->real_escape_string($name);
        $email = $conn->real_escape_string($email);
        $subject = $conn->real_escape_string($subject);
        $message = $conn->real_escape_string($message);
        
        $sql = "INSERT INTO contact_messages (name, email, subject, message) 
                VALUES ('$name', '$email', '$subject', '$message')";
        
        if ($conn->query($sql)) {
            $formSuccess = true;
            
            // Optional: Send email notification
            // mail('admin@shopverse.com', 'New Contact Form Submission', $message, "From: $email");
        } else {
            $formError = 'Terjadi kesalahan saat mengirim pesan. Silakan coba lagi.';
        }
    }
}

include 'includes/header.php';
?>

<!-- Page Header -->
<div class="bg-primary py-5 mb-5">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-md-6 text-white">
                <h1 class="display-4 font-weight-bold">Contact Us</h1>
                <p class="lead">Kami siap membantu Anda. Hubungi kami dengan pertanyaan, saran, atau masukan Anda.</p>
            </div>
            <div class="col-md-6 text-center">
                <img src="<?= SITE_URL ?>/assets/img/contact-illustration.svg" alt="Contact Us" class="img-fluid" style="max-height: 200px;">
            </div>
        </div>
    </div>
</div>

<!-- Contact Section -->
<div class="container py-5">
    <div class="row">
        <div class="col-lg-8">
            <div class="card shadow-sm mb-4">
                <div class="card-body">
                    <h3 class="mb-4">Kirim Pesan</h3>
                    
                    <?php if ($formSubmitted && $formSuccess): ?>
                        <div class="alert alert-success">
                            <i class="fas fa-check-circle mr-2"></i> Pesan Anda berhasil dikirim! Kami akan menghubungi Anda segera.
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($formError): ?>
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-circle mr-2"></i> <?= $formError ?>
                        </div>
                    <?php endif; ?>
                    
                    <form method="post" action="">
                        <div class="form-group">
                            <label for="name">Nama Lengkap</label>
                            <input type="text" class="form-control" id="name" name="name" placeholder="Masukkan nama lengkap Anda" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="email">Email</label>
                            <input type="email" class="form-control" id="email" name="email" placeholder="Masukkan alamat email Anda" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="subject">Subjek</label>
                            <input type="text" class="form-control" id="subject" name="subject" placeholder="Subjek pesan Anda" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="message">Pesan</label>
                            <textarea class="form-control" id="message" name="message" rows="5" placeholder="Tuliskan pesan Anda di sini" required></textarea>
                        </div>
                        
                        <button type="submit" name="contact_submit" class="btn btn-primary btn-lg">
                            <i class="fas fa-paper-plane mr-2"></i> Kirim Pesan
                        </button>
                    </form>
                </div>
            </div>
        </div>
        
        <div class="col-lg-4">
            <div class="card shadow-sm mb-4">
                <div class="card-body">
                    <h3 class="mb-4">Informasi Kontak</h3>
                    
                    <div class="d-flex mb-4">
                        <div class="mr-3 text-primary">
                            <i class="fas fa-map-marker-alt fa-2x"></i>
                        </div>
                        <div>
                            <h5>Alamat</h5>
                            <p class="mb-0">Jl. Raya Shopverse No. 123<br>Jakarta Selatan, 12345<br>Indonesia</p>
                        </div>
                    </div>
                    
                    <div class="d-flex mb-4">
                        <div class="mr-3 text-primary">
                            <i class="fas fa-phone-alt fa-2x"></i>
                        </div>
                        <div>
                            <h5>Telepon</h5>
                            <p class="mb-0">+62 21 1234 5678</p>
                            <p class="mb-0">+62 812 3456 7890 (WhatsApp)</p>
                        </div>
                    </div>
                    
                    <div class="d-flex mb-4">
                        <div class="mr-3 text-primary">
                            <i class="fas fa-envelope fa-2x"></i>
                        </div>
                        <div>
                            <h5>Email</h5>
                            <p class="mb-0">info@shopverse.com</p>
                            <p class="mb-0">support@shopverse.com</p>
                        </div>
                    </div>
                    
                    <div class="d-flex mb-4">
                        <div class="mr-3 text-primary">
                            <i class="fas fa-clock fa-2x"></i>
                        </div>
                        <div>
                            <h5>Jam Operasional</h5>
                            <p class="mb-0">Senin - Jumat: 09.00 - 17.00</p>
                            <p class="mb-0">Sabtu: 09.00 - 15.00</p>
                            <p class="mb-0">Minggu & Hari Libur: Tutup</p>
                        </div>
                    </div>
                    
                    <hr>
                    
                    <h5 class="mb-3">Ikuti Kami</h5>
                    <div class="social-icons">
                        <a href="#" class="btn btn-outline-primary mr-2"><i class="fab fa-facebook-f"></i></a>
                        <a href="#" class="btn btn-outline-primary mr-2"><i class="fab fa-twitter"></i></a>
                        <a href="#" class="btn btn-outline-primary mr-2"><i class="fab fa-instagram"></i></a>
                        <a href="#" class="btn btn-outline-primary mr-2"><i class="fab fa-linkedin-in"></i></a>
                        <a href="#" class="btn btn-outline-primary"><i class="fab fa-youtube"></i></a>
                    </div>
                </div>
            </div>
            
            <div class="card shadow-sm mb-4">
                <div class="card-body">
                    <h3 class="mb-3">Lokasi Kami</h3>
                    <div class="embed-responsive embed-responsive-16by9">
                        <iframe class="embed-responsive-item" src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d126920.29058339588!2d106.77754409999999!3d-6.229728100000001!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x2e69f3e945e34b9d%3A0x5371bf0fdad786a2!2sJakarta%20Selatan%2C%20Kota%20Jakarta%20Selatan%2C%20Daerah%20Khusus%20Ibukota%20Jakarta!5e0!3m2!1sid!2sid!4v1696922274317!5m2!1sid!2sid" frameborder="0" style="border:0;" allowfullscreen="" aria-hidden="false" tabindex="0"></iframe>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- FAQ Section -->
    <div class="row mt-5">
        <div class="col-12">
            <div class="card shadow-sm">
                <div class="card-body">
                    <h3 class="mb-4 text-center">Frequently Asked Questions</h3>
                    
                    <div class="accordion" id="faqAccordion">
                        <div class="card mb-2 border">
                            <div class="card-header bg-white" id="faqHeading1">
                                <h5 class="mb-0">
                                    <button class="btn btn-link text-dark text-decoration-none w-100 text-left" type="button" data-toggle="collapse" data-target="#faqCollapse1" aria-expanded="true" aria-controls="faqCollapse1">
                                        <i class="fas fa-question-circle mr-2 text-primary"></i> Bagaimana cara melakukan pembelian di ShopVerse?
                                    </button>
                                </h5>
                            </div>
                            <div id="faqCollapse1" class="collapse show" aria-labelledby="faqHeading1" data-parent="#faqAccordion">
                                <div class="card-body">
                                    Untuk melakukan pembelian di ShopVerse, Anda perlu mendaftar terlebih dahulu. Setelah login, pilih produk yang ingin dibeli, tambahkan ke keranjang, dan lanjutkan ke proses checkout. Pilih metode pembayaran yang tersedia dan selesaikan pesanan Anda.
                                </div>
                            </div>
                        </div>
                        
                        <div class="card mb-2 border">
                            <div class="card-header bg-white" id="faqHeading2">
                                <h5 class="mb-0">
                                    <button class="btn btn-link text-dark text-decoration-none w-100 text-left collapsed" type="button" data-toggle="collapse" data-target="#faqCollapse2" aria-expanded="false" aria-controls="faqCollapse2">
                                        <i class="fas fa-question-circle mr-2 text-primary"></i> Berapa lama waktu pengiriman produk?
                                    </button>
                                </h5>
                            </div>
                            <div id="faqCollapse2" class="collapse" aria-labelledby="faqHeading2" data-parent="#faqAccordion">
                                <div class="card-body">
                                    Waktu pengiriman produk tergantung pada lokasi Anda dan metode pengiriman yang dipilih. Secara umum, pengiriman dalam kota membutuhkan waktu 1-2 hari kerja, sementara pengiriman antar kota/pulau dapat memakan waktu 3-7 hari kerja.
                                </div>
                            </div>
                        </div>
                        
                        <div class="card mb-2 border">
                            <div class="card-header bg-white" id="faqHeading3">
                                <h5 class="mb-0">
                                    <button class="btn btn-link text-dark text-decoration-none w-100 text-left collapsed" type="button" data-toggle="collapse" data-target="#faqCollapse3" aria-expanded="false" aria-controls="faqCollapse3">
                                        <i class="fas fa-question-circle mr-2 text-primary"></i> Bagaimana jika produk yang saya terima rusak atau tidak sesuai?
                                    </button>
                                </h5>
                            </div>
                            <div id="faqCollapse3" class="collapse" aria-labelledby="faqHeading3" data-parent="#faqAccordion">
                                <div class="card-body">
                                    Jika produk yang Anda terima rusak atau tidak sesuai dengan deskripsi, Anda dapat mengajukan pengembalian atau penukaran dalam waktu 7 hari setelah produk diterima. Silakan hubungi customer service kami melalui halaman Contact Us atau email ke support@shopverse.com.
                                </div>
                            </div>
                        </div>
                        
                        <div class="card mb-2 border">
                            <div class="card-header bg-white" id="faqHeading4">
                                <h5 class="mb-0">
                                    <button class="btn btn-link text-dark text-decoration-none w-100 text-left collapsed" type="button" data-toggle="collapse" data-target="#faqCollapse4" aria-expanded="false" aria-controls="faqCollapse4">
                                        <i class="fas fa-question-circle mr-2 text-primary"></i> Metode pembayaran apa saja yang tersedia?
                                    </button>
                                </h5>
                            </div>
                            <div id="faqCollapse4" class="collapse" aria-labelledby="faqHeading4" data-parent="#faqAccordion">
                                <div class="card-body">
                                    ShopVerse menerima berbagai metode pembayaran, termasuk kartu kredit/debit, transfer bank, e-wallet (OVO, GoPay, Dana), dan COD (Cash on Delivery) untuk wilayah tertentu.
                                </div>
                            </div>
                        </div>
                        
                        <div class="card border">
                            <div class="card-header bg-white" id="faqHeading5">
                                <h5 class="mb-0">
                                    <button class="btn btn-link text-dark text-decoration-none w-100 text-left collapsed" type="button" data-toggle="collapse" data-target="#faqCollapse5" aria-expanded="false" aria-controls="faqCollapse5">
                                        <i class="fas fa-question-circle mr-2 text-primary"></i> Bagaimana cara menjadi vendor di ShopVerse?
                                    </button>
                                </h5>
                            </div>
                            <div id="faqCollapse5" class="collapse" aria-labelledby="faqHeading5" data-parent="#faqAccordion">
                                <div class="card-body">
                                    Untuk menjadi vendor di ShopVerse, Anda perlu mendaftar sebagai seller melalui halaman pendaftaran vendor. Isi semua informasi yang diperlukan, termasuk detail toko dan dokumen pendukung. Tim kami akan meninjau aplikasi Anda dan menghubungi Anda dalam 2-3 hari kerja.
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>