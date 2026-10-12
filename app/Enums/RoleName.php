<?php

namespace App\Enums;

/**
 * The canonical names of the four roles, exactly as they are stored in
 * roles.name (ERD v2.1 USERS/ROLES, AGENTS.md rule 8: plain tables, no
 * role-permission package).
 *
 * The transition matrix of Business Rules section 5 is written against
 * these values, so the strings "owner", "manager", "receptionist" and
 * "housekeeping" have exactly one definition here. The role seeder of
 * T-007 will read its names from this enum instead of repeating them.
 */
enum RoleName: string
{
    case Owner = 'owner';
    case Manager = 'manager';
    case Receptionist = 'receptionist';
    case Housekeeping = 'housekeeping';
}
