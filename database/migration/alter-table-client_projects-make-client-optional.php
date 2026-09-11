<?php

/**
 * SB-Tech — Make client projects independent of a client.
 *
 * A project is a first-class deliverable/deployment record that can be
 * created, edited, and tracked on its own; linking it to a client is an
 * optional association. `client_id` therefore becomes nullable and the
 * foreign key switches from CASCADE (client deletion destroys projects) to
 * SET NULL (deleting a client keeps the project, just unlinked — its history
 * survives).
 */

$query = [
    "ALTER TABLE `tbl_client_projects` DROP FOREIGN KEY `fk_project_client`;",

    "ALTER TABLE `tbl_client_projects`
     MODIFY COLUMN `client_id` INT DEFAULT NULL
     COMMENT 'Optional link to the owning client';",

    "ALTER TABLE `tbl_client_projects`
     ADD CONSTRAINT `fk_project_client`
     FOREIGN KEY (`client_id`) REFERENCES `tbl_clients` (`id`)
     ON DELETE SET NULL ON UPDATE CASCADE;",
];

$rollbackQuery = [
    "ALTER TABLE `tbl_client_projects` DROP FOREIGN KEY `fk_project_client`;",

    "ALTER TABLE `tbl_client_projects`
     MODIFY COLUMN `client_id` INT NOT NULL;",

    "ALTER TABLE `tbl_client_projects`
     ADD CONSTRAINT `fk_project_client`
     FOREIGN KEY (`client_id`) REFERENCES `tbl_clients` (`id`)
     ON DELETE CASCADE ON UPDATE CASCADE;",
];
