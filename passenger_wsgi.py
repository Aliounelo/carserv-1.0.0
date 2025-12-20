import os
import sys

# Ensure the app runs from this directory
os.chdir(os.path.dirname(__file__))
sys.path.insert(0, os.path.dirname(__file__))

from app import app as application
