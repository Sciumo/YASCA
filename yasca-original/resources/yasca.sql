create table scan (
    scan_id integer primary key,
    target_dir text,
    options text,
    scan_dt text,
    scan_by text
)

create table category (
    category_id integer primary key,
    name text,
    url text
)

create table description (
    description_id integer primary key,
    description text
)

create table result (
    result_id integer primary key,
    scan_id integer,
    category_id integer,
    severity integer,
    filename text,
    line_number integer,
    file_modify_dt text,
    description_id integer,
    message text,
    source_context text,
    active_fl text
)
