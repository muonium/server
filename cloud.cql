CREATE TABLE IF NOT EXISTS users (
	user_id uuid,
	login text,
	password text,
	email text,
	lang text,
	registration_date timestamp,
	last_connection timestamp,
	cek text,
	pks tuple<text, text>,
	double_auth boolean,
	auth_code text,
	PRIMARY KEY (user_id)
);

CREATE TABLE IF NOT EXISTS files (
	file_id uuid,
	owner_id uuid,
	owner_login text,
	folder_id uuid,
	updated_at timestamp,
	name text,
	size bigint,
	favorite boolean,
	trash boolean,
	expires timestamp,
	path text,
	dk text,
	lek map<uuid, text>,
	dk_asym map<uuid, text>,
	PRIMARY KEY ((file_id, owner_id), updated_at)
) WITH CLUSTERING ORDER BY (updated_at DESC);

CREATE TABLE IF NOT EXISTS folders (
	folder_id uuid,
	owner_id uuid,
	parent uuid,
	updated_at timestamp,
	name text,
	size bigint,
	favorite boolean,
	trash boolean,
	path text,
	PRIMARY KEY (folder_id, owner_id)
);

CREATE TABLE IF NOT EXISTS storage (
	user_id uuid,
	user_quota bigint,
	size_stored bigint,
	PRIMARY KEY (user_id)
);

CREATE TABLE IF NOT EXISTS storage_plans (
	product_id text,
	size bigint,
	price float,
	currency text,
	duration int,
	most_popular boolean,
	PRIMARY KEY (product_id)
);

CREATE TABLE IF NOT EXISTS upgrade (
	user_id uuid,
	txn_id text,
	size bigint,
	price float,
	currency text,
	start timestamp,
	end timestamp,
	removed boolean,
	PRIMARY KEY (user_id)
);

CREATE TABLE IF NOT EXISTS user_lostpass (
	user_id uuid,
	val_key text,
	expire timestamp,
	PRIMARY KEY (user_id)
);

CREATE TABLE IF NOT EXISTS user_validation (
	user_id uuid,
	val_key text,
	PRIMARY KEY (user_id)
);

-- ct means content

CREATE TABLE IF NOT EXISTS comments (
	comment_id uuid,
	file_id uuid,
	user_id uuid,
	added_date timestamp,
	ct text,
	PRIMARY KEY ((comment_id), added_date, file_id)
) WITH CLUSTERING ORDER BY (added_date DESC);

CREATE TABLE IF NOT EXISTS shared_with (
	file_id uuid,
	users list<uuid>,
	PRIMARY KEY (file_id)
);

CREATE TABLE IF NOT EXISTS contacts (
	user_id uuid,
	contacts list<uuid>,
	PRIMARY KEY (user_id)
);
