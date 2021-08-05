<?php
// The SQL to uninstall this tool
$DATABASE_UNINSTALL = array(
    // Nothing
);

// The SQL to create the tables if they don't exist
$DATABASE_INSTALL = array(
    array( "{$CFG->dbprefix}qp_poll",
        "create table {$CFG->dbprefix}qp_poll (
    poll_id             INTEGER NOT NULL AUTO_INCREMENT,
    user_id             INTEGER NOT NULL,
    context_id          INTEGER NOT NULL,
    question_text       TEXT NULL,
    allowchange         BOOL NOT NULL DEFAULT 0,
    hidesummary         BOOL NOT NULL DEFAULT 0,
    anonymous           BOOL NOT NULL DEFAULT 0,
    modified            datetime NULL,
    
    PRIMARY KEY(poll_id)
	
) ENGINE = InnoDB DEFAULT CHARSET=utf8"),
    array( "{$CFG->dbprefix}qp_choice",
        "create table {$CFG->dbprefix}qp_choice (
    choice_id       INTEGER NOT NULL AUTO_INCREMENT,
    poll_id         INTEGER NOT NULL,
	choice_text     TEXT NULL,
	choice_order    INTEGER NULL,
    modified        datetime NULL,
    
    CONSTRAINT `{$CFG->dbprefix}qp_fk_1`
        FOREIGN KEY (`poll_id`)
        REFERENCES `{$CFG->dbprefix}qp_poll` (`poll_id`)
        ON DELETE CASCADE,
    
    PRIMARY KEY(choice_id)
    
) ENGINE = InnoDB DEFAULT CHARSET=utf8"),
    array( "{$CFG->dbprefix}qp_response",
        "create table {$CFG->dbprefix}qp_response (
    response_id         INTEGER NOT NULL AUTO_INCREMENT,
    poll_id             INTEGER NOT NULL,
    user_id             INTEGER NOT NULL,
    choice_id           INTEGER NULL,
    sort_name           VARCHAR(255) NULL,   
	modified            datetime NULL,
    
    CONSTRAINT `{$CFG->dbprefix}qp_fk_2`
        FOREIGN KEY (`poll_id`)
        REFERENCES `{$CFG->dbprefix}qp_poll` (`poll_id`)
        ON DELETE CASCADE,
        
    CONSTRAINT `{$CFG->dbprefix}qp_fk_3`
        FOREIGN KEY (`choice_id`)
        REFERENCES `{$CFG->dbprefix}qp_choice` (`choice_id`), 
    
    PRIMARY KEY(response_id)
    
) ENGINE = InnoDB DEFAULT CHARSET=utf8")
);