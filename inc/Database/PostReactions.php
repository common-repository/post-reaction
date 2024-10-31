<?php

namespace BPPR\Database;

class PostReactions
{
    protected $table;
    protected $version = 1;
    protected $name = 'bppr_post_reactions';

    public function __construct(Table $table)
    {
        $this->table = $table;
    }

    public function getName()
    {
        global $wpdb;
        return $wpdb->prefix . $this->name;
    }

    /**
     * Add videos table
     * This is used for global video analytics
     *
     * @return void
     */
    public function install()
    {
        return $this->table->create($this->name, "
            id INT AUTO_INCREMENT PRIMARY KEY,
            post_id INT NOT NULL,
            user_id INT NOT NULL,
            reaction_type VARCHAR(20) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ", $this->version);
            // UNIQUE KEY unique_post_user_reaction (post_id, CASE WHEN user_id = 0 THEN NULL ELSE user_id END)
            // UNIQUE KEY unique_post_user_reaction (post_id, user_id)
    }

    /**
     * Uninstall tables
     *
     * @return void
     */
    public function uninstall()
    {
        $this->table->drop($this->getName());
    }
}



