<?php
// Set page title
$pageTitle = 'Categories';

// Include header
include_once 'includes/header.php';

// Redirect if not logged in
if (!isLoggedIn()) {
    $_SESSION['error_msg'] = 'Please log in to view categories.';
    $_SESSION['redirect_after_login'] = APP_URL . '/categories.php';
    redirect(APP_URL . '/login.php');
}

// Initialize database
$db = new Database();

// Handle form submission if admin
if (isAdmin() && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = sanitize($_POST['action']);
    
    if ($action === 'create_category') {
        // Create new category
        $name = sanitize($_POST['name']);
        $description = sanitize($_POST['description']);
        
        // Validate input
        if (empty($name)) {
            $_SESSION['error_msg'] = 'Category name is required.';
        } else {
            // Insert into database
            $db->query("INSERT INTO categories (name, description, created_by) 
                        VALUES (:name, :description, :created_by)");
            $db->bind(':name', $name);
            $db->bind(':description', $description);
            $db->bind(':created_by', $_SESSION['user_id']);
            
            if ($db->execute()) {
                $_SESSION['success_msg'] = 'Category created successfully.';
                redirect(APP_URL . '/categories.php');
            } else {
                $_SESSION['error_msg'] = 'Failed to create category.';
            }
        }
    } elseif ($action === 'update_category') {
        // Update existing category
        $categoryId = intval($_POST['category_id']);
        $name = sanitize($_POST['name']);
        $description = sanitize($_POST['description']);
        
        // Validate input
        if (empty($name)) {
            $_SESSION['error_msg'] = 'Category name is required.';
        } else {
            // Update database
            $db->query("UPDATE categories 
                        SET name = :name, description = :description
                        WHERE category_id = :category_id");
            $db->bind(':name', $name);
            $db->bind(':description', $description);
            $db->bind(':category_id', $categoryId);
            
            if ($db->execute()) {
                $_SESSION['success_msg'] = 'Category updated successfully.';
                redirect(APP_URL . '/categories.php');
            } else {
                $_SESSION['error_msg'] = 'Failed to update category.';
            }
        }
    } elseif ($action === 'delete_category') {
        // Delete category
        $categoryId = intval($_POST['category_id']);
        
        // First, check if there are flashcards in this category
        $db->query("SELECT COUNT(*) as card_count FROM flashcards WHERE category_id = :category_id");
        $db->bind(':category_id', $categoryId);
        $cardCount = $db->single()->card_count;
        
        if ($cardCount > 0) {
            $_SESSION['error_msg'] = 'Cannot delete category. It contains ' . $cardCount . ' flashcards.';
        } else {
            // Delete from database
            $db->query("DELETE FROM categories WHERE category_id = :category_id");
            $db->bind(':category_id', $categoryId);
            
            if ($db->execute()) {
                $_SESSION['success_msg'] = 'Category deleted successfully.';
                redirect(APP_URL . '/categories.php');
            } else {
                $_SESSION['error_msg'] = 'Failed to delete category.';
            }
        }
    }
}

// Get all categories with card count
$db->query("SELECT c.*, COUNT(f.card_id) as card_count 
            FROM categories c
            LEFT JOIN flashcards f ON c.category_id = f.category_id
            GROUP BY c.category_id
            ORDER BY c.name ASC");
$categories = $db->resultSet();

// Get action from URL (for admin functions)
$action = isset($_GET['action']) ? $_GET['action'] : '';
$categoryId = isset($_GET['id']) ? intval($_GET['id']) : 0;

// If editing or deleting, get category details
if (isAdmin() && ($action === 'edit' || $action === 'delete') && $categoryId > 0) {
    $db->query("SELECT * FROM categories WHERE category_id = :category_id");
    $db->bind(':category_id', $categoryId);
    $category = $db->single();
    
    if (!$category) {
        $_SESSION['error_msg'] = 'Category not found.';
        redirect(APP_URL . '/categories.php');
    }
}
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1><?php echo $pageTitle; ?></h1>
    
    <?php if (isAdmin()): ?>
    <div>
        <?php if ($action !== 'create'): ?>
        <a href="?action=create" class="btn btn-primary">Create New Category</a>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>

<?php if (isAdmin() && $action === 'create'): ?>
<!-- Create category form -->
<div class="row">
    <div class="col-md-8 mx-auto">
        <div class="card mb-4">
            <div class="card-header">
                <h2 class="h5 mb-0">Create New Category</h2>
            </div>
            <div class="card-body">
                <form action="categories.php" method="post" class="needs-validation" novalidate>
                    <input type="hidden" name="action" value="create_category">
                    
                    <div class="mb-3">
                        <label for="name" class="form-label">Category Name</label>
                        <input type="text" class="form-control" id="name" name="name" required>
                        <div class="invalid-feedback">Please enter a category name.</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="description" class="form-label">Description</label>
                        <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                    </div>
                    
                    <div class="d-flex justify-content-between">
                        <a href="categories.php" class="btn btn-secondary">Cancel</a>
                        <button type="submit" class="btn btn-primary">Create Category</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<?php elseif (isAdmin() && $action === 'edit' && isset($category)): ?>
<!-- Edit category form -->
<div class="row">
    <div class="col-md-8 mx-auto">
        <div class="card mb-4">
            <div class="card-header">
                <h2 class="h5 mb-0">Edit Category</h2>
            </div>
            <div class="card-body">
                <form action="categories.php" method="post" class="needs-validation" novalidate>
                    <input type="hidden" name="action" value="update_category">
                    <input type="hidden" name="category_id" value="<?php echo $category->category_id; ?>">
                    
                    <div class="mb-3">
                        <label for="name" class="form-label">Category Name</label>
                        <input type="text" class="form-control" id="name" name="name" value="<?php echo htmlspecialchars($category->name); ?>" required>
                        <div class="invalid-feedback">Please enter a category name.</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="description" class="form-label">Description</label>
                        <textarea class="form-control" id="description" name="description" rows="3"><?php echo htmlspecialchars($category->description); ?></textarea>
                    </div>
                    
                    <div class="d-flex justify-content-between">
                        <a href="categories.php" class="btn btn-secondary">Cancel</a>
                        <button type="submit" class="btn btn-primary">Update Category</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<?php elseif (isAdmin() && $action === 'delete' && isset($category)): ?>
<!-- Delete category confirmation -->
<div class="row">
    <div class="col-md-8 mx-auto">
        <div class="card mb-4">
            <div class="card-header">
                <h2 class="h5 mb-0">Delete Category</h2>
            </div>
            <div class="card-body">
                <p>Are you sure you want to delete the category <strong><?php echo htmlspecialchars($category->name); ?></strong>?</p>
                
                <div class="alert alert-warning">
                    <p class="mb-0">This action cannot be undone. Categories can only be deleted if they contain no flashcards.</p>
                </div>
                
                <form action="categories.php" method="post">
                    <input type="hidden" name="action" value="delete_category">
                    <input type="hidden" name="category_id" value="<?php echo $category->category_id; ?>">
                    
                    <div class="d-flex justify-content-between">
                        <a href="categories.php" class="btn btn-secondary">Cancel</a>
                        <button type="submit" class="btn btn-danger">Delete Category</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<?php else: ?>
<!-- Categories list -->
<div class="row g-4">
    <?php foreach ($categories as $category): ?>
    <div class="col-md-4">
        <div class="card h-100 category-card">
            <div class="card-body">
                <h2 class="h5 card-title"><?php echo htmlspecialchars($category->name); ?></h2>
                
                <p class="card-text">
                    <?php echo $category->description ? htmlspecialchars($category->description) : 'No description available.'; ?>
                </p>
                
                <p class="text-muted">
                    <?php echo $category->card_count; ?> <?php echo $category->card_count === 1 ? 'flashcard' : 'flashcards'; ?>
                </p>
                
                <div class="mt-auto">
                    <a href="study.php?category=<?php echo $category->category_id; ?>" class="btn btn-primary">Study</a>
                    
                    <?php if (isAdmin()): ?>
                    <div class="btn-group mt-2">
                        <a href="?action=edit&id=<?php echo $category->category_id; ?>" class="btn btn-sm btn-outline-secondary">Edit</a>
                        <?php if ($category->card_count === 0): ?>
                        <a href="?action=delete&id=<?php echo $category->category_id; ?>" class="btn btn-sm btn-outline-danger">Delete</a>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
    
    <?php if (empty($categories)): ?>
    <div class="col-12">
        <div class="alert alert-info">
            <p class="mb-0">No categories found. <?php echo isAdmin() ? 'Create your first category to get started.' : 'Please check back later.'; ?></p>
        </div>
    </div>
    <?php endif; ?>
</div>
<?php endif; ?>

<?php
// Include footer
include_once 'includes/footer.php';
?>