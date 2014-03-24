#require 'animate-sass'
#require 'sassy-buttons'
#require 'zen-grids'
#require 'zurb-foundation'
#require 'susy'
require 'bootstrap-sass'
#require 'font-awesome-sass'
require 'chosen.scss'
#require 'bourbon'
require 'neat'

environment = :development

# Require any additional compass plugins here.

# Set this to the root of your project when deployed:
#http_path = "public"
#http_fonts_dir = "fonts"
css_dir = "public/css"
#sass_dir = "app"
sass_dir = "app/Stylesheets"
fonts_dir = "fonts"
images_dir = "app/assets/img"
javascripts_dir = "app/assets/js"
cache_path = "tmp/sass"

# You can select your preferred output style here (can be overridden via the command line):
# output_style = :expanded or :nested or :compact or :compressed
#output_style = (environment == :development) ? :expanded : :compressed
output_style = :compact
line_comments = true
project_type = :stand_alone

# To enable relative paths to assets via compass helper functions. Uncomment:
relative_assets = true

# To disable debugging comments that display the original location of your selectors. Uncomment:
# line_comments = false

#add_import_path "src/AppTogether"
add_import_path "app/assets/sass"

# If you prefer the indented syntax, you might want to regenerate this
# project again passing --syntax sass, or you can uncomment this:
# preferred_syntax = :sass
# and then run:
# sass-convert -R --from scss --to sass sass scss && rm -rf sass && mv scss sass
