<?php
require_once 'config/constants.php';
require_once 'config/database.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$database = new Database();
$db = $database->getConnection();

$community_id = $_GET['id'] ?? null;
if (!$community_id) {
    header("Location: communities.php");
    exit();
}

// Get community details
$stmt = $db->prepare("
    SELECT c.*, 
           CONCAT(u.first_name, ' ', u.last_name) as creator_name,
           COUNT(cm.user_id) as member_count,
           (SELECT COUNT(*) FROM community_members WHERE community_id = c.id AND user_id = ?) as is_member,
           (SELECT role FROM community_members WHERE community_id = c.id AND user_id = ?) as user_role
    FROM communities c
    LEFT JOIN users u ON c.created_by = u.id
    LEFT JOIN community_members cm ON c.id = cm.community_id
    WHERE c.id = ?
    GROUP BY c.id
");
$stmt->execute([$_SESSION['user_id'], $_SESSION['user_id'], $community_id]);
$community = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$community) {
    header("Location: communities.php");
    exit();
}

// Get posts dengan pagination
$page = $_GET['page'] ?? 1;
$limit = 10;
$offset = ($page - 1) * $limit;

$postsStmt = $db->prepare("
    SELECT p.*, 
           CONCAT(u.first_name, ' ', u.last_name) as author_name,
           u.id as author_id,
           COUNT(pc.id) as comment_count
    FROM community_posts p
    LEFT JOIN users u ON p.user_id = u.id
    LEFT JOIN post_comments pc ON p.id = pc.post_id
    WHERE p.community_id = ? AND p.is_deleted = FALSE
    GROUP BY p.id
    ORDER BY p.created_at DESC
    LIMIT $limit OFFSET $offset
");

$postsStmt->execute([$community_id]);
$posts = $postsStmt->fetchAll(PDO::FETCH_ASSOC);

// Get total posts count
$countStmt = $db->prepare("SELECT COUNT(*) as total FROM community_posts WHERE community_id = ?");
$countStmt->execute([$community_id]);
$totalPosts = $countStmt->fetch()['total'];
$totalPages = ceil($totalPosts / $limit);

// Get user info
$user_name = $_SESSION['user_name'];
$user_initials = strtoupper(substr(explode(' ', $user_name)[0], 0, 1));
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($community['name']); ?> - Resensiku</title>
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
                    <input type="text" class="search-input" name="search" placeholder="Cari community...">
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
                                <span>Komunitas Saya</span>
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
            <!-- Back Button -->
            <button class="back-button" onclick="goBackToCommunities()">
                <i class="fas fa-arrow-left"></i>
                Kembali ke Semua Community
            </button>

            <!-- Community Header -->
            <div class="community-header-enhanced">
                <div class="community-cover-large">
                    <img src="assets/images/communities/<?php echo $community['cover_image'] ?? 'default-community.jpg'; ?>" 
                         alt="<?php echo htmlspecialchars($community['name']); ?>"
                         onerror="this.src='assets/images/communities/default-community.jpg'">
                </div>
                <div class="community-info-large">
                    <h1 class="community-title"><?php echo htmlspecialchars($community['name']); ?></h1>
                    <p class="community-description-large"><?php echo htmlspecialchars($community['description']); ?></p>
                    <div class="community-stats">
                        <div class="stat">
                            <i class="fas fa-users"></i>
                            <span><?php echo $community['member_count']; ?> Anggota</span>
                        </div>
                        <div class="stat">
                            <i class="fas fa-user"></i>
                            <span>Dibuat oleh <?php echo htmlspecialchars($community['creator_name']); ?></span>
                        </div>
                        <?php if ($community['user_role'] === 'admin'): ?>
                            <div class="stat">
                                <i class="fas fa-crown"></i>
                                <span>Admin</span>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="community-actions-large">
                        <div class="action-group">
                            <?php if ($community['is_member']): ?>
                                <button class="btn btn-danger btn-lg" 
                                        onclick="leaveCommunity(<?php echo $community['id']; ?>, '<?php echo htmlspecialchars($community['name']); ?>')">
                                    <i class="fas fa-sign-out-alt"></i>
                                    Keluar Community
                                </button>
                                <button class="btn btn-primary btn-lg" onclick="openCreatePostModal()">
                                    <i class="fas fa-plus"></i>
                                    Buat Post Baru
                                </button>
                            <?php else: ?>
                                <button class="btn btn-primary btn-lg" 
                                        onclick="joinCommunity(<?php echo $community['id']; ?>, '<?php echo htmlspecialchars($community['name']); ?>')">
                                    <i class="fas fa-plus"></i>
                                    Gabung Community
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Posts Feed -->
            <?php if ($community['is_member']): ?>
                <div class="posts-feed">
                    <h2 class="section-title">Diskusi Community</h2>
                    
                    <?php if (empty($posts)): ?>
                        <div class="posts-empty">
                            <div class="empty-icon">
                                <i class="fas fa-comments"></i>
                            </div>
                            <h3>Belum Ada Diskusi</h3>
                            <p>Jadilah yang pertama memulai diskusi di community ini</p>
                            <button class="btn btn-primary btn-lg" onclick="openCreatePostModal()">
                                <i class="fas fa-plus"></i>
                                Mulai Diskusi Pertama
                            </button>
                        </div>
                    <?php else: ?>
                        <?php foreach ($posts as $post): ?>
                        <div class="post-card" data-post-id="<?php echo $post['id']; ?>">
                            <div class="post-header">
                                <div class="post-author">
                                    <div class="author-avatar">
                                        <?php echo strtoupper(substr($post['author_name'], 0, 1)); ?>
                                    </div>
                                    <div class="author-info">
                                        <h4 class="author-name"><?php echo htmlspecialchars($post['author_name']); ?></h4>
                                        <span class="post-date"><?php echo date('d M Y H:i', strtotime($post['created_at'])); ?></span>
                                    </div>
                                </div>
                                
                                <!-- Tombol Delete (hanya tampil untuk author atau admin) -->
                                <?php if ($post['author_id'] == $_SESSION['user_id'] || $community['user_role'] === 'admin' || $community['user_role'] === 'moderator'): ?>
                                <div class="post-actions-dropdown">
                                    <button class="post-menu-btn" onclick="togglePostMenu(<?php echo $post['id']; ?>)">
                                        <i class="fas fa-ellipsis-v"></i>
                                    </button>
                                    <div class="post-menu" id="post-menu-<?php echo $post['id']; ?>">
                                        <button class="post-menu-item post-menu-delete" onclick="deletePost(<?php echo $post['id']; ?>, '<?php echo htmlspecialchars($post['title'] ?: 'post ini'); ?>')">
                                            <i class="fas fa-trash"></i>
                                            Hapus Post
                                        </button>
                                    </div>
                                </div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="post-content">
                                <?php if ($post['title']): ?>
                                    <h3 class="post-title"><?php echo htmlspecialchars($post['title']); ?></h3>
                                <?php endif; ?>
                                <p class="post-text"><?php echo nl2br(htmlspecialchars($post['content'])); ?></p>
                                
                                <?php if ($post['image']): ?>
                                    <div class="post-image">
                                        <img src="assets/images/posts/<?php echo $post['image']; ?>" 
                                             alt="Post image"
                                             onclick="openImageModal(this.src)">
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="post-actions">
                                <button class="post-action-btn" onclick="toggleComments(<?php echo $post['id']; ?>)">
                                    <i class="fas fa-comment"></i>
                                    <span><?php echo $post['comment_count']; ?> Komentar</span>
                                </button>
                            </div>
                            
                            <!-- Comments Section -->
                            <div class="comments-section" id="comments-<?php echo $post['id']; ?>" style="display: none;">
                                <div class="comments-list" id="comments-list-<?php echo $post['id']; ?>">
                                    <!-- Comments will be loaded here -->
                                </div>
                                <div class="comment-form">
                                    <div class="comment-input-group">
                                        <input type="text" 
                                               class="comment-input" 
                                               placeholder="Tulis komentar..." 
                                               id="comment-input-<?php echo $post['id']; ?>">
                                        <button class="btn btn-primary btn-sm" onclick="addComment(<?php echo $post['id']; ?>)">
                                            <i class="fas fa-paper-plane"></i>
                                        </button>
                                    </div>
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
                        <a href="community-detail.php?id=<?php echo $community_id; ?>&page=<?php echo $page - 1; ?>" class="pagination-btn">
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
                            <a href="community-detail.php?id=<?php echo $community_id; ?>&page=<?php echo $i; ?>" 
                               class="pagination-number <?php echo $i == $page ? 'active' : ''; ?>">
                                <?php echo $i; ?>
                            </a>
                        <?php endfor; ?>
                    </div>

                    <?php if ($page < $totalPages): ?>
                        <a href="community-detail.php?id=<?php echo $community_id; ?>&page=<?php echo $page + 1; ?>" class="pagination-btn">
                            Selanjutnya
                            <i class="fas fa-chevron-right"></i>
                        </a>
                    <?php endif; ?>
                </div>
                <?php endif; ?>

            <?php else: ?>
                <div class="join-prompt">
                    <div class="join-prompt-content">
                        <i class="fas fa-lock"></i>
                        <h3>Bergabung untuk Melihat Diskusi</h3>
                        <p>Gabung community ini untuk melihat dan berpartisipasi dalam diskusi</p>
                        <button class="btn btn-primary btn-lg" onclick="joinCommunity(<?php echo $community['id']; ?>, '<?php echo htmlspecialchars($community['name']); ?>')">
                            <i class="fas fa-plus"></i>
                            Gabung Community
                        </button>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <!-- Create Post Modal -->
    <div id="createPostModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Buat Post Baru</h2>
                <button class="modal-close" onclick="closeCreatePostModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <form id="createPostForm" action="actions/create_post.php" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="community_id" value="<?php echo $community_id; ?>">
                <div class="modal-body">
                    <div class="form-group">
                        <label for="postTitle">Judul (Opsional)</label>
                        <input type="text" id="postTitle" name="title" maxlength="500" placeholder="Judul post (opsional)">
                    </div>
                    <div class="form-group">
                        <label for="postContent">Konten *</label>
                        <textarea id="postContent" name="content" rows="6" placeholder="Apa yang ingin Anda diskusikan?"></textarea>
                    </div>
                    <div class="form-group">
                        <label for="postImage">Gambar (Opsional)</label>
                        <input type="file" id="postImage" name="image" accept="image/*">
                        <small class="form-help">Format: JPG, PNG, GIF. Maksimal 2MB</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeCreatePostModal()">Batal</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-paper-plane"></i>
                        Posting
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Image Modal -->
    <div id="imageModal" class="modal">
        <div class="modal-content image-modal-content">
            <button class="modal-close" onclick="closeImageModal()">
                <i class="fas fa-times"></i>
            </button>
            <img id="modalImage" src="" alt="Preview">
        </div>
    </div>

    <script src="assets/js/community-detail.js"></script>
</body>
</html>