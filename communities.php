<?php
require_once 'config/constants.php';
require_once 'config/database.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$database = new Database();
$db = $database->getConnection();

$search = $_GET['search'] ?? '';

// Get all communities dengan pagination
$page = $_GET['page'] ?? 1;
$limit = 12;
$offset = ($page - 1) * $limit;

// Build query
$whereConditions = [];
$params = [];

if (!empty($search)) {
    $whereConditions[] = "(c.name LIKE ? OR c.description LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$whereClause = '';
if (!empty($whereConditions)) {
    $whereClause = "WHERE " . implode(" AND ", $whereConditions);
}

// Get communities
$communitiesQuery = "
    SELECT c.*, 
           CONCAT(u.first_name, ' ', u.last_name) as creator_name,
           COUNT(cm.user_id) as member_count,
           (SELECT COUNT(*) FROM community_members WHERE community_id = c.id AND user_id = ?) as is_member
    FROM communities c
    LEFT JOIN users u ON c.created_by = u.id
    LEFT JOIN community_members cm ON c.id = cm.community_id
    $whereClause
    GROUP BY c.id
    ORDER BY c.created_at DESC
    LIMIT $limit OFFSET $offset
";

$params = array_merge([$_SESSION['user_id']], $params);
$stmt = $db->prepare($communitiesQuery);
$stmt->execute($params);
$communities = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get total count for pagination
$countQuery = "
    SELECT COUNT(DISTINCT c.id) as total
    FROM communities c
    LEFT JOIN users u ON c.created_by = u.id
    $whereClause
";
$countStmt = $db->prepare($countQuery);
$countStmt->execute(array_slice($params, 1));
$totalCommunities = $countStmt->fetch()['total'];
$totalPages = ceil($totalCommunities / $limit);

// Get user info
$user_name = $_SESSION['user_name'];
$user_initials = strtoupper(substr(explode(' ', $user_name)[0], 0, 1));
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Community - Resensiku</title>
    <link rel="stylesheet" href="assets/css/main.css">
    <link rel="stylesheet" href="assets/css/header.css">
    <link rel="stylesheet" href="assets/css/communities.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <!-- Header -->
    <header class="user-header">
        <div class="header-content">
            <!-- Logo -->
            <div class="logo-section">
                <a href="index.php">
                    <img src="assets/images/logo/Resensiku.png" alt="Resensiku" class="logo">
                </a>
            </div>

            <!-- Search Bar -->
            <div class="search-section">
                <form class="search-form" method="GET" action="communities.php">
                    <i class="fas fa-search search-icon"></i>
                    <input type="text" class="search-input" name="search" placeholder="Cari community..." value="<?php echo htmlspecialchars($search); ?>">
                </form>
            </div>

            <!-- Navigation -->
            <div class="right-section">
                <nav class="main-nav">
                    <a href="index.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'index.php' ? 'active' : ''; ?>">
                    <i class="fas fa-home"></i>
                    <span>Home</span>
                </a>
                    <a href="books.php" class="nav-item">
                        <i class="fas fa-tags"></i>
                        <span>Genre</span>
                    </a>
                    <a href="bookshelf.php" class="nav-item">
                        <i class="fas fa-bookmark"></i>
                        <span>Bookshelf</span>
                    </a>
                    <a href="communities.php" class="nav-item active">
                        <i class="fas fa-users"></i>
                        <span>Community</span>
                    </a>
                </nav>

                <!-- User Dropdown -->
                <div class="user-nav">
                    <div class="user-dropdown">
                        <button class="user-trigger">
                            <div class="user-avatar"><?php echo $user_initials; ?></div>
                            <span><?php echo explode(' ', $user_name)[0]; ?></span>
                            <i class="fas fa-chevron-down"></i>
                        </button>
                        <div class="dropdown-menu">
                            <a href="books.php" class="dropdown-item">
                                <i class="fas fa-tags"></i>
                                <span>Jelajahi Genre</span>
                            </a>
                            <a href="bookshelf.php" class="dropdown-item">
                                <i class="fas fa-bookmark"></i>
                                <span>Bookshelf Saya</span>
                            </a>
                            <a href="communities.php" class="dropdown-item">
                            <i class="fas fa-users"></i>
                            <span>Bookshelf Saya</span>
                        </a>
                            <div class="dropdown-divider"></div>
                            <a href="logout.php" class="dropdown-item">
                                <i class="fas fa-sign-out-alt"></i>
                                <span>Logout</span>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <main class="main-content">
        <div class="communities-container">
            <!-- Page Header -->
            <div class="page-header">
                <div class="page-title-section">
                    <h1 class="page-title">Book Communities</h1>
                    <p class="page-subtitle">Bergabunglah dengan komunitas pembaca dan diskusikan buku favorit Anda</p>
                    <?php if (!empty($search)): ?>
                        <p class="search-results">Menampilkan hasil pencarian untuk "<?php echo htmlspecialchars($search); ?>"</p>
                    <?php endif; ?>
                </div>
                <div class="page-actions">
                    <button class="btn btn-primary btn-lg" onclick="openCreateCommunityModal()">
                        <i class="fas fa-plus"></i>
                        Buat Community Baru
                    </button>
                </div>
            </div>

            <!-- Communities Grid -->
            <div class="communities-grid">
                <?php if (empty($communities)): ?>
                    <div class="communities-empty">
                        <div class="empty-icon">
                            <i class="fas fa-users"></i>
                        </div>
                        <h3><?php echo empty($search) ? 'Belum Ada Community' : 'Community Tidak Ditemukan'; ?></h3>
                        <p>
                            <?php if (empty($search)): ?>
                                Jadilah yang pertama membuat community untuk diskusi buku
                            <?php else: ?>
                                Tidak ada community yang cocok dengan pencarian "<?php echo htmlspecialchars($search); ?>"
                            <?php endif; ?>
                        </p>
                        <button class="btn btn-primary btn-lg" onclick="openCreateCommunityModal()">
                            <i class="fas fa-plus"></i>
                            Buat Community Pertama
                        </button>
                    </div>
                <?php else: ?>
                    <?php foreach ($communities as $community): ?>
                    <div class="community-card" onclick="window.location.href='community-detail.php?id=<?php echo $community['id']; ?>'">
                        <div class="community-cover">
                            <img src="assets/images/communities/<?php echo $community['cover_image'] ?? 'default-community.jpg'; ?>" 
                                 alt="<?php echo htmlspecialchars($community['name']); ?>"
                                 onerror="this.src='assets/images/communities/default-community.jpg'">
                            <?php if ($community['is_member']): ?>
                                <div class="community-status">
                                    <i class="fas fa-check-circle"></i>
                                    Anggota
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="community-info">
                            <h3 class="community-name"><?php echo htmlspecialchars($community['name']); ?></h3>
                            <p class="community-description"><?php echo htmlspecialchars($community['description']); ?></p>
                            <div class="community-meta">
                                <div class="community-creator">
                                    <i class="fas fa-user"></i>
                                    <?php echo htmlspecialchars($community['creator_name']); ?>
                                </div>
                                <div class="community-members">
                                    <i class="fas fa-users"></i>
                                    <?php echo $community['member_count']; ?> anggota
                                </div>
                            </div>
                            <div class="community-actions">
                                <?php if ($community['is_member']): ?>
                                    <button class="btn btn-secondary btn-sm" onclick="event.stopPropagation(); leaveCommunity(<?php echo $community['id']; ?>, '<?php echo htmlspecialchars($community['name']); ?>')">
                                        <i class="fas fa-sign-out-alt"></i>
                                        Keluar
                                    </button>
                                <?php else: ?>
                                    <button class="btn btn-primary btn-sm" onclick="event.stopPropagation(); joinCommunity(<?php echo $community['id']; ?>, '<?php echo htmlspecialchars($community['name']); ?>')">
                                        <i class="fas fa-plus"></i>
                                        Gabung
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <!-- Pagination -->
            <?php if ($totalPages > 1): ?>
            <div class="pagination">
                <?php if ($page > 1): ?>
                    <a href="communities.php?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>" class="pagination-btn">
                        <i class="fas fa-chevron-left"></i>
                        Sebelumnya
                    </a>
                <?php endif; ?>

                <div class="pagination-numbers">
                    <?php
                    $startPage = max(1, $page - 2);
                    $endPage = min($totalPages, $page + 2);
                    
                    for ($i = $startPage; $i <= $endPage; $i++):
                    ?>
                        <a href="communities.php?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>" 
                           class="pagination-number <?php echo $i == $page ? 'active' : ''; ?>">
                            <?php echo $i; ?>
                        </a>
                    <?php endfor; ?>
                </div>

                <?php if ($page < $totalPages): ?>
                    <a href="communities.php?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>" class="pagination-btn">
                        Selanjutnya
                        <i class="fas fa-chevron-right"></i>
                    </a>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
    </main>

    <!-- Create Community Modal -->
    <div id="createCommunityModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Buat Community Baru</h2>
                <button class="modal-close" onclick="closeCreateCommunityModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <form id="createCommunityForm" action="actions/create_community.php" method="POST" enctype="multipart/form-data">
                <div class="modal-body">
                    <div class="form-group">
                        <label for="communityName">Nama Community *</label>
                        <input type="text" id="communityName" name="name" required maxlength="255" placeholder="Masukkan nama community">
                    </div>
                    <div class="form-group">
                        <label for="communityDescription">Deskripsi *</label>
                        <textarea id="communityDescription" name="description" rows="4" placeholder="Deskripsikan tentang community ini" required></textarea>
                    </div>
                    <div class="form-group">
                        <label for="communityCover">Cover Image (Opsional)</label>
                        <input type="file" id="communityCover" name="cover_image" accept="image/*">
                        <small class="form-help">Format: JPG, PNG, GIF. Maksimal 2MB</small>
                    </div>
                    <div class="form-group">
                        <label class="checkbox-label">
                            <input type="checkbox" name="is_public" checked>
                            <span class="checkmark"></span>
                            Community Publik (semua orang bisa bergabung)
                        </label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeCreateCommunityModal()">Batal</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-plus"></i>
                        Buat Community
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script src="assets/js/communities.js"></script>
</body>
</html>