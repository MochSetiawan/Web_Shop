<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';

$pageTitle = 'About Us';
include 'includes/header.php';
?>

<!-- Page Header -->
<div class="bg-primary py-5 mb-5">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-md-6 text-white">
                <h1 class="display-4 font-weight-bold">About Us</h1>
                <p class="lead">Membangun Masa Depan E-Commerce Bersama ShopVerse</p>
            </div>
            <div class="col-md-6 text-center">
                <img src="<?= SITE_URL ?>/assets/img/about-illustration.svg" alt="About Us" class="img-fluid" style="max-height: 200px;">
            </div>
        </div>
    </div>
</div>

<!-- About Section -->
<div class="container py-5">
    <!-- Our Story -->
    <div class="row mb-5">
        <div class="col-lg-6 mb-4 mb-lg-0">
            <img src="<?= SITE_URL ?>/assets/img/about-story.jpg" alt="Our Story" class="img-fluid rounded shadow-sm">
        </div>
        <div class="col-lg-6">
            <div class="pl-lg-4">
                <h2 class="mb-4">Cerita Kami</h2>
                <p class="text-muted">ShopVerse didirikan pada tahun 2021 oleh sekelompok pengusaha muda yang memiliki visi untuk merevolusi cara berbelanja online di Indonesia. Berawal dari sebuah ruang kecil di Jakarta, kami membangun platform yang mempertemukan penjual lokal dengan pembeli dari seluruh Indonesia.</p>
                <p class="text-muted">Perjalanan kami dimulai dengan hanya 50 vendor dan beberapa ratus produk. Dalam waktu singkat, ShopVerse telah berkembang menjadi marketplace dengan lebih dari 10.000 vendor aktif dan jutaan produk yang tersedia dalam berbagai kategori.</p>
                <p class="text-muted">Nama "ShopVerse" terinspirasi dari visi kami untuk menciptakan semesta belanja (shopping universe) yang tanpa batas, di mana setiap penjual memiliki kesempatan yang sama untuk sukses dan setiap pembeli dapat menemukan produk yang mereka butuhkan dengan mudah dan aman.</p>
            </div>
        </div>
    </div>

    <!-- Vision & Mission -->
    <div class="row mb-5">
        <div class="col-12 text-center mb-4">
            <h2 class="mb-4">Visi & Misi</h2>
        </div>
        <div class="col-md-6 mb-4">
            <div class="card h-100 border-0 shadow-sm">
                <div class="card-body text-center p-5">
                    <div class="mb-4">
                        <i class="fas fa-eye fa-3x text-primary"></i>
                    </div>
                    <h3 class="h4 mb-3">Visi Kami</h3>
                    <p class="text-muted mb-0">Menjadi platform e-commerce terkemuka di Indonesia yang menghubungkan penjual lokal dengan pasar global, mendorong pertumbuhan ekonomi digital, dan menciptakan peluang bagi semua orang untuk sukses dalam dunia digital.</p>
                </div>
            </div>
        </div>
        <div class="col-md-6 mb-4">
            <div class="card h-100 border-0 shadow-sm">
                <div class="card-body text-center p-5">
                    <div class="mb-4">
                        <i class="fas fa-bullseye fa-3x text-primary"></i>
                    </div>
                    <h3 class="h4 mb-3">Misi Kami</h3>
                    <p class="text-muted mb-0">Menyediakan platform e-commerce yang aman, mudah digunakan, dan terpercaya yang memungkinkan penjual untuk mengembangkan bisnis mereka dan pembeli untuk menemukan produk berkualitas dengan harga terbaik. Kami berkomitmen untuk mendukung UMKM Indonesia dan berinovasi terus-menerus untuk meningkatkan pengalaman berbelanja online.</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Our Values -->
    <div class="row mb-5">
        <div class="col-12 text-center mb-4">
            <h2 class="mb-4">Nilai-Nilai Kami</h2>
        </div>
        <div class="col-md-3 mb-4">
            <div class="card h-100 border-0 shadow-sm">
                <div class="card-body text-center p-4">
                    <div class="icon-box mb-3">
                        <i class="fas fa-user-shield fa-2x text-primary"></i>
                    </div>
                    <h4 class="h5">Kepercayaan</h4>
                    <p class="small text-muted mb-0">Kami membangun kepercayaan melalui transparansi, keamanan, dan kualitas layanan.</p>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-4">
            <div class="card h-100 border-0 shadow-sm">
                <div class="card-body text-center p-4">
                    <div class="icon-box mb-3">
                        <i class="fas fa-lightbulb fa-2x text-primary"></i>
                    </div>
                    <h4 class="h5">Inovasi</h4>
                    <p class="small text-muted mb-0">Kami terus berinovasi untuk meningkatkan pengalaman pengguna dan mengadopsi teknologi terbaru.</p>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-4">
            <div class="card h-100 border-0 shadow-sm">
                <div class="card-body text-center p-4">
                    <div class="icon-box mb-3">
                        <i class="fas fa-handshake fa-2x text-primary"></i>
                    </div>
                    <h4 class="h5">Kolaborasi</h4>
                    <p class="small text-muted mb-0">Kami percaya bahwa kesuksesan datang dari kerja sama dan membangun hubungan yang kuat.</p>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-4">
            <div class="card h-100 border-0 shadow-sm">
                <div class="card-body text-center p-4">
                    <div class="icon-box mb-3">
                        <i class="fas fa-hand-holding-heart fa-2x text-primary"></i>
                    </div>
                    <h4 class="h5">Pemberdayaan</h4>
                    <p class="small text-muted mb-0">Kami berkomitmen untuk memberdayakan UMKM dan penjual lokal untuk berkembang di era digital.</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Statistics -->
    <div class="row mb-5">
        <div class="col-12">
            <div class="bg-light p-5 rounded shadow-sm">
                <div class="row text-center">
                    <div class="col-lg-3 col-md-6 mb-4 mb-lg-0">
                        <div class="counter">
                            <i class="fas fa-users fa-2x text-primary mb-3"></i>
                            <h2 class="h1 font-weight-bold" data-count="150000">150,000+</h2>
                            <p class="text-muted">Pengguna Aktif</p>
                        </div>
                    </div>
                    <div class="col-lg-3 col-md-6 mb-4 mb-lg-0">
                        <div class="counter">
                            <i class="fas fa-store fa-2x text-primary mb-3"></i>
                            <h2 class="h1 font-weight-bold" data-count="10000">10,000+</h2>
                            <p class="text-muted">Vendor Terdaftar</p>
                        </div>
                    </div>
                    <div class="col-lg-3 col-md-6 mb-4 mb-md-0">
                        <div class="counter">
                            <i class="fas fa-box fa-2x text-primary mb-3"></i>
                            <h2 class="h1 font-weight-bold" data-count="500000">500,000+</h2>
                            <p class="text-muted">Produk Tersedia</p>
                        </div>
                    </div>
                    <div class="col-lg-3 col-md-6">
                        <div class="counter">
                            <i class="fas fa-map-marker-alt fa-2x text-primary mb-3"></i>
                            <h2 class="h1 font-weight-bold" data-count="34">34</h2>
                            <p class="text-muted">Provinsi Terjangkau</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Team Section -->
    <div class="row mb-5">
        <div class="col-12 text-center mb-4">
            <h2 class="mb-4">Tim Pendiri</h2>
            <p class="lead text-muted mb-5">Orang-orang hebat di balik kesuksesan ShopVerse</p>
        </div>
        
        <div class="col-lg-3 col-md-6 mb-4">
            <div class="card border-0 shadow-sm">
                <img src="<?= SITE_URL ?>/assets/img/team/founder1.jpg" class="card-img-top" alt="Founder">
                <div class="card-body text-center">
                    <h5 class="card-title mb-0">Ahmad Rizky</h5>
                    <p class="text-muted small mb-2">CEO & Co-Founder</p>
                    <p class="card-text small">Ahmad memiliki pengalaman lebih dari 10 tahun di industri teknologi dan e-commerce. Sebelumnya bekerja di Tokopedia dan Bukalapak.</p>
                    <div class="social-icons mt-3">
                        <a href="#" class="text-primary mx-1"><i class="fab fa-linkedin-in"></i></a>
                        <a href="#" class="text-primary mx-1"><i class="fab fa-twitter"></i></a>
                        <a href="#" class="text-primary mx-1"><i class="fab fa-instagram"></i></a>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-lg-3 col-md-6 mb-4">
            <div class="card border-0 shadow-sm">
                <img src="<?= SITE_URL ?>/assets/img/team/founder2.jpg" class="card-img-top" alt="Founder">
                <div class="card-body text-center">
                    <h5 class="card-title mb-0">Siti Nurhaliza</h5>
                    <p class="text-muted small mb-2">COO & Co-Founder</p>
                    <p class="card-text small">Siti adalah ahli strategis bisnis dengan latar belakang di manajemen operasional dan logistik dari berbagai perusahaan multinasional.</p>
                    <div class="social-icons mt-3">
                        <a href="#" class="text-primary mx-1"><i class="fab fa-linkedin-in"></i></a>
                        <a href="#" class="text-primary mx-1"><i class="fab fa-twitter"></i></a>
                        <a href="#" class="text-primary mx-1"><i class="fab fa-instagram"></i></a>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-lg-3 col-md-6 mb-4">
            <div class="card border-0 shadow-sm">
                <img src="<?= SITE_URL ?>/assets/img/team/founder3.jpg" class="card-img-top" alt="Founder">
                <div class="card-body text-center">
                    <h5 class="card-title mb-0">Budi Santoso</h5>
                    <p class="text-muted small mb-2">CTO & Co-Founder</p>
                    <p class="card-text small">Budi adalah developer dengan pengalaman lebih dari 15 tahun membangun infrastruktur teknologi untuk perusahaan-perusahaan besar.</p>
                    <div class="social-icons mt-3">
                        <a href="#" class="text-primary mx-1"><i class="fab fa-linkedin-in"></i></a>
                        <a href="#" class="text-primary mx-1"><i class="fab fa-github"></i></a>
                        <a href="#" class="text-primary mx-1"><i class="fab fa-twitter"></i></a>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-lg-3 col-md-6 mb-4">
            <div class="card border-0 shadow-sm">
                <img src="<?= SITE_URL ?>/assets/img/team/founder4.jpg" class="card-img-top" alt="Founder">
                <div class="card-body text-center">
                    <h5 class="card-title mb-0">Dewi Anggraini</h5>
                    <p class="text-muted small mb-2">CMO & Co-Founder</p>
                    <p class="card-text small">Dewi adalah ahli pemasaran digital yang telah membantu banyak brand nasional dan internasional meningkatkan presence online mereka.</p>
                    <div class="social-icons mt-3">
                        <a href="#" class="text-primary mx-1"><i class="fab fa-linkedin-in"></i></a>
                        <a href="#" class="text-primary mx-1"><i class="fab fa-twitter"></i></a>
                        <a href="#" class="text-primary mx-1"><i class="fab fa-instagram"></i></a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Achievement Timeline -->
    <div class="row mb-5">
        <div class="col-12 text-center mb-4">
            <h2 class="mb-4">Perjalanan Kami</h2>
        </div>
        <div class="col-12">
            <div class="timeline">
                <div class="timeline-container left">
                    <div class="content">
                        <h4>2021</h4>
                        <p>ShopVerse didirikan di Jakarta. Platform beta diluncurkan dengan 50 vendor dan 500 produk.</p>
                    </div>
                </div>
                <div class="timeline-container right">
                    <div class="content">
                        <h4>2022</h4>
                        <p>ShopVerse mencapai 1000 vendor aktif dan 100.000 pengguna terdaftar. Sistem pembayaran terintegrasi diluncurkan.</p>
                    </div>
                </div>
                <div class="timeline-container left">
                    <div class="content">
                        <h4>2023</h4>
                        <p>ShopVerse memperluas jangkauan ke seluruh Indonesia. Menerima pendanaan seri A sebesar $5 juta dari investor lokal dan internasional.</p>
                    </div>
                </div>
                <div class="timeline-container right">
                    <div class="content">
                        <h4>2024</h4>
                        <p>Peluncuran aplikasi mobile ShopVerse. Mencapai 10.000 vendor dan 500.000 produk. Dianugerahi "E-Commerce Terbaik untuk UMKM" oleh Kementerian Kominfo.</p>
                    </div>
                </div>
                <div class="timeline-container left">
                    <div class="content">
                        <h4>2025</h4>
                        <p>ShopVerse merencanakan ekspansi ke pasar Asia Tenggara, dengan target 25.000 vendor dan 1 juta pengguna aktif sebelum akhir tahun.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Testimonials -->
    <div class="row mb-5">
        <div class="col-12 text-center mb-4">
            <h2 class="mb-4">Testimoni Partner Kami</h2>
        </div>
        <div class="col-md-4 mb-4 mb-md-0">
            <div class="card h-100 border-0 shadow-sm">
                <div class="card-body p-4">
                    <div class="d-flex align-items-center mb-4">
                        <img src="<?= SITE_URL ?>/assets/img/testimonials/testimonial1.jpg" alt="Testimonial" class="rounded-circle mr-3" width="60">
                        <div>
                            <h5 class="mb-0">Joko Widodo</h5>
                            <p class="text-muted small mb-0">Pemilik Toko Furniture Jaya</p>
                        </div>
                    </div>
                    <p class="mb-0">"ShopVerse telah mengubah bisnis saya. Dalam 6 bulan bergabung, penjualan saya meningkat 200%. Platform yang mudah digunakan dan dukungan tim yang luar biasa!"</p>
                    <div class="mt-3 text-warning">
                        <i class="fas fa-star"></i>
                        <i class="fas fa-star"></i>
                        <i class="fas fa-star"></i>
                        <i class="fas fa-star"></i>
                        <i class="fas fa-star"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4 mb-4 mb-md-0">
            <div class="card h-100 border-0 shadow-sm">
                <div class="card-body p-4">
                    <div class="d-flex align-items-center mb-4">
                        <img src="<?= SITE_URL ?>/assets/img/testimonials/testimonial2.jpg" alt="Testimonial" class="rounded-circle mr-3" width="60">
                        <div>
                            <h5 class="mb-0">Rina Marlina</h5>
                            <p class="text-muted small mb-0">Pengusaha Fashion</p>
                        </div>
                    </div>
                    <p class="mb-0">"Sebagai desainer fashion pemula, ShopVerse memberi saya kesempatan untuk menampilkan karya saya ke seluruh Indonesia. Fitur promosi dan sistem pembayaran yang aman sangat membantu bisnis saya."</p>
                    <div class="mt-3 text-warning">
                        <i class="fas fa-star"></i>
                        <i class="fas fa-star"></i>
                        <i class="fas fa-star"></i>
                        <i class="fas fa-star"></i>
                        <i class="fas fa-star-half-alt"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card h-100 border-0 shadow-sm">
                <div class="card-body p-4">
                    <div class="d-flex align-items-center mb-4">
                        <img src="<?= SITE_URL ?>/assets/img/testimonials/testimonial3.jpg" alt="Testimonial" class="rounded-circle mr-3" width="60">
                        <div>
                            <h5 class="mb-0">Hendra Gunawan</h5>
                            <p class="text-muted small mb-0">Pemilik Toko Elektronik</p>
                        </div>
                    </div>
                    <p class="mb-0">"Setelah 10 tahun berbisnis offline, ShopVerse membantu saya beralih ke online dengan mulus. Dashboard vendor yang lengkap dan laporan penjualan real-time sangat membantu saya mengelola inventaris."</p>
                    <div class="mt-3 text-warning">
                        <i class="fas fa-star"></i>
                        <i class="fas fa-star"></i>
                        <i class="fas fa-star"></i>
                        <i class="fas fa-star"></i>
                        <i class="fas fa-star"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Call to Action -->
    <div class="row">
        <div class="col-12">
            <div class="bg-primary rounded shadow p-5 text-center text-white">
                <h2 class="mb-4">Bergabunglah Dengan ShopVerse</h2>
                <p class="lead mb-4">Jadilah bagian dari revolusi e-commerce Indonesia. Daftarkan toko Anda atau mulai berbelanja sekarang!</p>
                <div class="mt-4">
                    <a href="<?= SITE_URL ?>/register.php?type=vendor" class="btn btn-light btn-lg mr-3 mb-2 mb-md-0">
                        <i class="fas fa-store mr-2"></i> Daftar Sebagai Vendor
                    </a>
                    <a href="<?= SITE_URL ?>/register.php" class="btn btn-outline-light btn-lg">
                        <i class="fas fa-user mr-2"></i> Daftar Sebagai Pembeli
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
/* Timeline Styling */
.timeline {
    position: relative;
    max-width: 1200px;
    margin: 0 auto;
}

.timeline::after {
    content: '';
    position: absolute;
    width: 6px;
    background-color: #4e73df;
    top: 0;
    bottom: 0;
    left: 50%;
    margin-left: -3px;
    border-radius: 10px;
}

.timeline-container {
    padding: 10px 40px;
    position: relative;
    background-color: inherit;
    width: 50%;
}

.timeline-container::after {
    content: '';
    position: absolute;
    width: 20px;
    height: 20px;
    right: -10px;
    background-color: white;
    border: 4px solid #4e73df;
    top: 15px;
    border-radius: 50%;
    z-index: 1;
}

.left {
    left: 0;
}

.right {
    left: 50%;
}

.left::before {
    content: " ";
    height: 0;
    position: absolute;
    top: 22px;
    width: 0;
    z-index: 1;
    right: 30px;
    border: medium solid #f8f9fc;
    border-width: 10px 0 10px 10px;
    border-color: transparent transparent transparent #f8f9fc;
}

.right::before {
    content: " ";
    height: 0;
    position: absolute;
    top: 22px;
    width: 0;
    z-index: 1;
    left: 30px;
    border: medium solid #f8f9fc;
    border-width: 10px 10px 10px 0;
    border-color: transparent #f8f9fc transparent transparent;
}

.right::after {
    left: -10px;
}

.content {
    padding: 20px;
    background-color: #f8f9fc;
    position: relative;
    border-radius: 6px;
    box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.1);
}

@media screen and (max-width: 768px) {
    .timeline::after {
        left: 31px;
    }
    
    .timeline-container {
        width: 100%;
        padding-left: 70px;
        padding-right: 25px;
    }
    
    .timeline-container::before {
        left: 60px;
        border: medium solid #f8f9fc;
        border-width: 10px 10px 10px 0;
        border-color: transparent #f8f9fc transparent transparent;
    }

    .left::after, .right::after {
        left: 21px;
    }
    
    .right {
        left: 0%;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Counter Animation
    const counters = document.querySelectorAll('.counter h2');
    const speed = 200;
    
    counters.forEach(counter => {
        const updateCount = () => {
            const target = +counter.getAttribute('data-count');
            const count = +counter.innerText.replace(/,|[+]/g, '');
            const inc = target / speed;
            
            if (count < target) {
                counter.innerText = Math.ceil(count + inc).toLocaleString() + '+';
                setTimeout(updateCount, 1);
            } else {
                counter.innerText = target.toLocaleString() + '+';
            }
        };
        
        updateCount();
    });
});
</script>

<?php include 'includes/footer.php'; ?>