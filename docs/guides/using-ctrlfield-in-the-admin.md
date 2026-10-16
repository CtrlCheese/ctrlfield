# Using CtrlField in the admin — step by step

This guide builds a complete content type without writing code: a **post type**, a **taxonomy** and a **field group**, all from **WP Admin → CtrlField**. Two steps, like in ACF:

1. **Post Types**: create the content type ("Examples").
2. **Field Groups**: create its fields and assign them to that post type.

> Working with a developer? Anything made here can be exported to PHP and versioned in git later. See [Using CtrlField in code](./using-ctrlfield-in-code.md).

---

## Step 1: Create the post type

1. Go to **CtrlField → Post Types** and click **Add New Post Type**.
2. **Names**:
   - **Plural name**: `Examples`. This is what the menu shows.
   - **Singular name**: `Example`.
   - **Key**: filled in from the singular name (`example`). You can leave it as it is. If the key is reserved by WordPress or already in use, the form says so while you type.
3. **Admin menu**: click the icon to choose one, and pick where it goes in the menu.
4. **Editor**: tick what the edit screen should have.
   - **Title** only gives a clean form with just your fields, like *Depoimentos*.
   - **Content editor** adds the block editor (Gutenberg) above your fields.
   - **Featured image**, **Excerpt**, **Revisions**… as needed.
5. **Website & URLs**:
   - **Public**: entries get their own page, e.g. `/example/my-entry/`. Leave it off for admin-only content.
   - **Archive page**: a page listing every entry, e.g. `/example/`.
   - **Hierarchical**: entries can have a parent, like Pages.
   - **Block editor & REST API**: keep it on if you ticked *Content editor*.
6. **Taxonomies**: tick existing ones, such as **Categories** or **Tags**, to use them with Examples.
7. Click **Create Post Type**. The sidebar shows the URLs it will use before you save.

The confirmation has a **Next: add fields to Examples** link, which goes straight to step 2.

**Examples** now appears in the menu. With two or more post types, they are grouped under one **CPTs** menu item, sorted alphabetically. You can switch this off at the bottom of **CtrlField → Post Types**.

### Optional: a taxonomy of its own

1. **CtrlField → Post Types → Taxonomies** tab → **Add New Taxonomy**.
2. Names: `Services` / `Service`.
3. **Kind**: *Like categories*, where terms can have parents and editors tick them, or *Like tags*, where editors type them.
4. **Used with**: tick **Examples**.
5. **Create Taxonomy**. It appears under Examples in the menu.

---

## Step 2: Add fields to the post type

1. Open **CtrlField → Post Types → Examples**. In its **Fields** section, click **+ New field group for Examples**.
   - Or go to **CtrlField → Field Groups → Add New Field Group** and set the location yourself.
2. **Title** and **key** are already filled in (`Example`, `example_fields`). Change them if you like.
3. **Location** decides where the fields appear. It is already set to **Post type** **is equal to** **Examples**.
4. **Add fields**, one per piece of information. For each one:
   - **Label**: what editors see, e.g. "Headline".
   - **Name**: filled in from the label (`headline`). Developers use it to read the value.
   - **Type**: text, image, select, date, link, map…
   - **Required**, **Instructions** (help text under the label) and **Width** (25–100%, to put fields side by side).
5. Long groups are easier to fill in with a **Tab** field: every field after it, until the next tab, goes on that tab. **Accordion** works the same way, as collapsible sections.
6. To show a field only in some cases, use **Conditional logic**. For example, "Featured note" is shown only when "Featured" is on.
7. Click **Save**.

Open **Examples → Add New**: your fields are there. Back in **Post Types → Examples**, the **Fields** section now lists the group, with **Edit fields**.

### Pro fields (with a Pro license)

| Field | Use it for |
|---|---|
| **Repeater** | A list of rows with the same fields, e.g. team members with photo, name and role |
| **Flexible content** | Page sections of different kinds (text, quote, gallery…) in any order |
| **Gallery** | Several images, sortable |
| **Clone** | Reusing the fields of a *library* group. To create one, add a field group and set **Location** to *Nowhere on its own — a library group*. **Clone from** lists only library groups. |

---

## Step 3: Fill in an entry

1. **Examples → Add New**.
2. Fill in the fields. A required field left empty is marked when you save.
3. **Publish**.

---

## Useful screens

- **CtrlField → Schema Inspector**:
  - **Overview** lists every field group, where it shows and how often each field is filled in.
  - **Post values** shows exactly what is saved for one entry.
  - **Health** lists problems, each with a fix you can preview first.
  - **Import / Export** moves entries to and from CSV, so you can edit them in a spreadsheet.
- **Field Groups → Template code** shows what a developer writes to display the fields.
- **Field Groups → Export PHP → Save as PHP file** and **Post Types → Export PHP** hand your work over to code, so a developer can version it in git.

---

## Troubleshooting

| Problem | Why |
|---|---|
| The edit screen shows only the block editor, no fields | The field group has no fields yet, or its **Location** rule points to another post type. **Post Types → the post type → Fields** shows which groups it has and flags empty ones. |
| A field is missing from a group | A *Clone* field whose library group was deleted or got location rules is left out, and the rest of the group still shows. **Schema Inspector → Health** names it. |
| The post type is marked "Not active" | A post type with the same key is already defined in code, and code takes precedence |
| Health says "Field group files can be downloaded" | CtrlField could not write to the field group folder, so it could not protect the files. Make the folder writable and open the admin again; or add the nginx rule shown there, or move the folder outside the web root with `CTRLFIELD_JSON_PATH`. Your content and the database are not exposed. |
| New URLs show "Page not found" | Visit **Settings → Permalinks** once and click **Save** |
