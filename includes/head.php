<style>
    /* General Profile Styles */
    .profile-container {
        display: flex;
        gap: 30px;
        max-width: 1200px;
        margin: 0 auto;
        padding: 20px;
    }
    
    /* Profile Sidebar Styles */
    .profile-sidebar {
        width: 250px;
        background-color: #f8f9fa;
        padding: 20px;
        border-radius: 8px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    }
    
    .profile-sidebar h2 {
        margin-bottom: 20px;
        font-size: 1.4rem;
        color: #333;
        border-bottom: 1px solid #e0e0e0;
        padding-bottom: 10px;
    }
    
    .profile-nav ul {
        list-style: none;
        padding: 0;
        margin: 0;
    }
    
    .profile-nav li {
        margin-bottom: 8px;
    }
    
    .profile-nav a {
        display: block;
        padding: 10px 15px;
        color: #555;
        border-radius: 5px;
        text-decoration: none;
        transition: all 0.3s ease;
    }
    
    .profile-nav a:hover {
        background-color: #e9ecef;
        color: #007bff;
    }
    
    .profile-nav li.active a {
        background-color: #007bff;
        color: white;
    }
    
    .profile-sidebar-footer {
        margin-top: 30px;
        padding-top: 15px;
        border-top: 1px solid #e0e0e0;
    }
    
    /* Profile Content Styles */
    .profile-content {
        flex: 1;
        background-color: #fff;
        padding: 25px;
        border-radius: 8px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    }
    
    /* Interests Page Styles */
    .interests-container {
        margin-top: 20px;
    }
    
    .interests-category {
        margin-bottom: 30px;
    }
    
    .interests-category h3 {
        margin-bottom: 15px;
        padding-bottom: 8px;
        border-bottom: 1px solid #e0e0e0;
    }
    
    .interests-list {
        display: flex;
        flex-wrap: wrap;
        gap: 15px;
    }
    
    .interest-item {
        position: relative;
        padding: 10px 15px;
        background-color: #f8f9fa;
        border: 1px solid #e0e0e0;
        border-radius: 5px;
        cursor: pointer;
        transition: all 0.3s ease;
    }
    
    .interest-item:hover {
        background-color: #e9ecef;
    }
    
    .interest-item.selected {
        background-color: #cce5ff;
        border-color: #b8daff;
    }
    
    .interest-checkbox {
        position: absolute;
        opacity: 0;
        cursor: pointer;
    }
    
    .interest-name {
        font-weight: 500;
    }
    
    .submit-interests {
        margin-top: 20px;
    }
    
    .alert {
        padding: 12px 20px;
        margin-bottom: 20px;
        border-radius: 5px;
    }
    
    .alert-success {
        background-color: #d4edda;
        color: #155724;
        border: 1px solid #c3e6cb;
    }
    
    .alert-danger {
        background-color: #f8d7da;
        color: #721c24;
        border: 1px solid #f5c6cb;
    }
</style> 