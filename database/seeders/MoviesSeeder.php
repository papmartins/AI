<?php

namespace Database\Seeders;

use App\Models\Movie;
use App\Models\Genre;
use Illuminate\Database\Seeder;

class MoviesSeeder extends Seeder
{
    private array $realMovies = [
        // Action
        ['title'=>'Die Hard','description'=>'NYPD officer fights terrorists in a Los Angeles skyscraper.','year'=>1988,'genre_id'=>1,'age_rating'=>'16','cast'=>'Bruce Willis, Alan Rickman, Bonnie Bedelia','director'=>'John McTiernan'],
        ['title'=>'Mad Max: Fury Road','description'=>'In a post-apocalyptic wasteland, a woman flees a tyrant with his captives.','year'=>2015,'genre_id'=>1,'age_rating'=>'16','cast'=>'Tom Hardy, Charlize Theron, Nicholas Hoult','director'=>'George Miller'],
        ['title'=>'John Wick','description'=>'A retired hitman returns to the underworld to seek vengeance.','year'=>2014,'genre_id'=>1,'age_rating'=>'18','cast'=>'Keanu Reeves, Michael Nyqvist, Alfie Allen','director'=>'Chad Stahelski'],
        ['title'=>'The Dark Knight','description'=>'Batman faces the Joker as Gotham descends into chaos.','year'=>2008,'genre_id'=>1,'age_rating'=>'13','cast'=>'Christian Bale, Heath Ledger, Aaron Eckhart','director'=>'Christopher Nolan'],
        ['title'=>'Gladiator','description'=>'A Roman general seeks revenge after being betrayed and enslaved.','year'=>2000,'genre_id'=>1,'age_rating'=>'16','cast'=>'Russell Crowe, Joaquin Phoenix, Connie Nielsen','director'=>'Ridley Scott'],
        ['title'=>'Terminator 2: Judgment Day','description'=>'A cyborg protects a boy destined to lead humanity.','year'=>1991,'genre_id'=>1,'age_rating'=>'16','cast'=>'Arnold Schwarzenegger, Linda Hamilton, Edward Furlong','director'=>'James Cameron'],
        ['title'=>'The Matrix','description'=>'A hacker learns reality is a simulation and joins a rebellion.','year'=>1999,'genre_id'=>1,'age_rating'=>'16','cast'=>'Keanu Reeves, Laurence Fishburne, Carrie-Anne Moss','director'=>'Lana Wachowski, Lilly Wachowski'],
        ['title'=>'Raiders of the Lost Ark','description'=>'An archaeologist races Nazis to find the Ark of the Covenant.','year'=>1981,'genre_id'=>1,'age_rating'=>'10','cast'=>'Harrison Ford, Karen Allen, Paul Freeman','director'=>'Steven Spielberg'],

        // Drama
        ['title'=>'The Shawshank Redemption','description'=>'Two imprisoned men bond over years, finding hope and redemption.','year'=>1994,'genre_id'=>2,'age_rating'=>'16','cast'=>'Tim Robbins, Morgan Freeman, Bob Gunton','director'=>'Frank Darabont'],
        ['title'=>'Forrest Gump','description'=>'A man with a simple outlook witnesses and shapes historic events.','year'=>1994,'genre_id'=>2,'age_rating'=>'13','cast'=>'Tom Hanks, Robin Wright, Gary Sinise','director'=>'Robert Zemeckis'],
        ['title'=>'Fight Club','description'=>'An office worker forms an underground fight club that spirals.','year'=>1999,'genre_id'=>2,'age_rating'=>'18','cast'=>'Brad Pitt, Edward Norton, Helena Bonham Carter','director'=>'David Fincher'],
        ['title'=>'The Godfather','description'=>'The aging patriarch of a crime dynasty hands control to his son.','year'=>1972,'genre_id'=>2,'age_rating'=>'18','cast'=>'Marlon Brando, Al Pacino, James Caan','director'=>'Francis Ford Coppola'],
        ['title'=>'Whiplash','description'=>'A young drummer endures a ruthless instructor to achieve greatness.','year'=>2014,'genre_id'=>2,'age_rating'=>'16','cast'=>'Miles Teller, J.K. Simmons, Paul Reiser','director'=>'Damien Chazelle'],
        ['title'=>'Schindler\'s List','description'=>'A businessman saves Jewish refugees during the Holocaust.','year'=>1993,'genre_id'=>2,'age_rating'=>'16','cast'=>'Liam Neeson, Ralph Fiennes, Ben Kingsley','director'=>'Steven Spielberg'],
        ['title'=>'The Social Network','description'=>'The founding of Facebook sparks ambition, rivalry, and lawsuits.','year'=>2010,'genre_id'=>2,'age_rating'=>'13','cast'=>'Jesse Eisenberg, Andrew Garfield, Justin Timberlake','director'=>'David Fincher'],
        ['title'=>'There Will Be Blood','description'=>'A ruthless oilman rises, driven by greed and obsession.','year'=>2007,'genre_id'=>2,'age_rating'=>'16','cast'=>'Daniel Day-Lewis, Paul Dano, Ciarán Hinds','director'=>'Paul Thomas Anderson'],

        // Comedy
        ['title'=>'The Big Lebowski','description'=>'A laid-back man gets pulled into a bizarre kidnapping plot.','year'=>1998,'genre_id'=>3,'age_rating'=>'16','cast'=>'Jeff Bridges, John Goodman, Julianne Moore','director'=>'Joel Coen, Ethan Coen'],
        ['title'=>'Superbad','description'=>'Two friends chase one last wild night before graduation.','year'=>2007,'genre_id'=>3,'age_rating'=>'16','cast'=>'Jonah Hill, Michael Cera, Christopher Mintz-Plasse','director'=>'Greg Mottola'],
        ['title'=>'Step Brothers','description'=>'Two adult men become stepbrothers and refuse to grow up.','year'=>2008,'genre_id'=>3,'age_rating'=>'16','cast'=>'Will Ferrell, John C. Reilly, Mary Steenburgen','director'=>'Adam McKay'],
        ['title'=>'Anchorman: The Legend of Ron Burgundy','description'=>'A 1970s news anchor faces chaos when a female reporter joins.','year'=>2004,'genre_id'=>3,'age_rating'=>'13','cast'=>'Will Ferrell, Christina Applegate, Paul Rudd','director'=>'Adam McKay'],
        ['title'=>'Groundhog Day','description'=>'A weatherman relives the same day until he changes himself.','year'=>1993,'genre_id'=>3,'age_rating'=>'10','cast'=>'Bill Murray, Andie MacDowell, Chris Elliott','director'=>'Harold Ramis'],
        ['title'=>'Hot Fuzz','description'=>'A top cop uncovers dark secrets in a “perfect” village.','year'=>2007,'genre_id'=>3,'age_rating'=>'16','cast'=>'Simon Pegg, Nick Frost, Jim Broadbent','director'=>'Edgar Wright'],
        ['title'=>'Bridesmaids','description'=>'Friendship and chaos erupt around a wedding party.','year'=>2011,'genre_id'=>3,'age_rating'=>'16','cast'=>'Kristen Wiig, Maya Rudolph, Melissa McCarthy','director'=>'Paul Feig'],

        // Horror
        ['title'=>'The Shining','description'=>'A writer and his family face terrifying forces in a remote hotel.','year'=>1980,'genre_id'=>4,'age_rating'=>'18','cast'=>'Jack Nicholson, Shelley Duvall, Danny Lloyd','director'=>'Stanley Kubrick'],
        ['title'=>'Get Out','description'=>'A man visits his girlfriend’s family and uncovers a sinister plot.','year'=>2017,'genre_id'=>4,'age_rating'=>'16','cast'=>'Daniel Kaluuya, Allison Williams, Bradley Whitford','director'=>'Jordan Peele'],
        ['title'=>'A Nightmare on Elm Street','description'=>'Teens are hunted in their dreams by a vengeful killer.','year'=>1984,'genre_id'=>4,'age_rating'=>'18','cast'=>'Heather Langenkamp, Robert Englund, Johnny Depp','director'=>'Wes Craven'],
        ['title'=>'Hereditary','description'=>'A family is haunted by disturbing events after a death.','year'=>2018,'genre_id'=>4,'age_rating'=>'18','cast'=>'Toni Collette, Alex Wolff, Milly Shapiro','director'=>'Ari Aster'],
        ['title'=>'The Exorcist','description'=>'A mother seeks help when her daughter shows signs of possession.','year'=>1973,'genre_id'=>4,'age_rating'=>'18','cast'=>'Ellen Burstyn, Linda Blair, Max von Sydow','director'=>'William Friedkin'],
        ['title'=>'Alien','description'=>'A crew is hunted by a deadly extraterrestrial aboard their ship.','year'=>1979,'genre_id'=>4,'age_rating'=>'16','cast'=>'Sigourney Weaver, Tom Skerritt, John Hurt','director'=>'Ridley Scott'],
        ['title'=>'The Thing','description'=>'An Antarctic team confronts a shape-shifting alien.','year'=>1982,'genre_id'=>4,'age_rating'=>'18','cast'=>'Kurt Russell, Wilford Brimley, Keith David','director'=>'John Carpenter'],

        // Romance
        ['title'=>'The Notebook','description'=>'A love story tested by time, class, and memory.','year'=>2004,'genre_id'=>5,'age_rating'=>'13','cast'=>'Ryan Gosling, Rachel McAdams, James Garner','director'=>'Nick Cassavetes'],
        ['title'=>'La La Land','description'=>'Two artists fall in love while chasing dreams in Los Angeles.','year'=>2016,'genre_id'=>5,'age_rating'=>'10','cast'=>'Ryan Gosling, Emma Stone, John Legend','director'=>'Damien Chazelle'],
        ['title'=>'Titanic','description'=>'A romance blossoms aboard the doomed ocean liner.','year'=>1997,'genre_id'=>5,'age_rating'=>'13','cast'=>'Leonardo DiCaprio, Kate Winslet, Billy Zane','director'=>'James Cameron'],
        ['title'=>'Pride & Prejudice','description'=>'A spirited woman navigates love and expectations in Regency England.','year'=>2005,'genre_id'=>5,'age_rating'=>'10','cast'=>'Keira Knightley, Matthew Macfadyen, Brenda Blethyn','director'=>'Joe Wright'],
        ['title'=>'Before Sunrise','description'=>'Two strangers connect deeply over one night in Vienna.','year'=>1995,'genre_id'=>5,'age_rating'=>'13','cast'=>'Ethan Hawke, Julie Delpy, Andrea Eckert','director'=>'Richard Linklater'],
        ['title'=>'Eternal Sunshine of the Spotless Mind','description'=>'A couple erases memories of each other and confronts love again.','year'=>2004,'genre_id'=>5,'age_rating'=>'13','cast'=>'Jim Carrey, Kate Winslet, Kirsten Dunst','director'=>'Michel Gondry'],
        ['title'=>'Casablanca','description'=>'Old lovers reunite amid World War II intrigue in Morocco.','year'=>1942,'genre_id'=>5,'age_rating'=>'10','cast'=>'Humphrey Bogart, Ingrid Bergman, Paul Henreid','director'=>'Michael Curtiz'],

        // Sci-Fi
        ['title'=>'Inception','description'=>'A thief enters dreams to plant an idea in a target’s mind.','year'=>2010,'genre_id'=>6,'age_rating'=>'13','cast'=>'Leonardo DiCaprio, Joseph Gordon-Levitt, Tom Hardy','director'=>'Christopher Nolan'],
        ['title'=>'Interstellar','description'=>'Explorers travel through a wormhole to save humanity’s future.','year'=>2014,'genre_id'=>6,'age_rating'=>'10','cast'=>'Matthew McConaughey, Anne Hathaway, Jessica Chastain','director'=>'Christopher Nolan'],
        ['title'=>'Blade Runner','description'=>'A cop hunts bioengineered beings in a dystopian future.','year'=>1982,'genre_id'=>6,'age_rating'=>'16','cast'=>'Harrison Ford, Rutger Hauer, Sean Young','director'=>'Ridley Scott'],
        ['title'=>'Arrival','description'=>'A linguist tries to communicate with aliens as global tensions rise.','year'=>2016,'genre_id'=>6,'age_rating'=>'13','cast'=>'Amy Adams, Jeremy Renner, Forest Whitaker','director'=>'Denis Villeneuve'],
        ['title'=>'Ex Machina','description'=>'A programmer tests an AI with unsettling results.','year'=>2014,'genre_id'=>6,'age_rating'=>'16','cast'=>'Domhnall Gleeson, Alicia Vikander, Oscar Isaac','director'=>'Alex Garland'],
        ['title'=>'Moon','description'=>'A lunar worker faces a life-changing discovery near the end of his contract.','year'=>2009,'genre_id'=>6,'age_rating'=>'13','cast'=>'Sam Rockwell, Kevin Spacey, Dominique McElligott','director'=>'Duncan Jones'],
        ['title'=>'Her','description'=>'A lonely man falls in love with an intelligent operating system.','year'=>2013,'genre_id'=>6,'age_rating'=>'13','cast'=>'Joaquin Phoenix, Scarlett Johansson, Amy Adams','director'=>'Spike Jonze'],

        // Thriller
        ['title'=>'Se7en','description'=>'Detectives hunt a killer who uses the seven deadly sins as motives.','year'=>1995,'genre_id'=>7,'age_rating'=>'18','cast'=>'Morgan Freeman, Brad Pitt, Kevin Spacey','director'=>'David Fincher'],
        ['title'=>'The Silence of the Lambs','description'=>'An FBI trainee consults a cannibal killer to catch another murderer.','year'=>1991,'genre_id'=>7,'age_rating'=>'18','cast'=>'Jodie Foster, Anthony Hopkins, Scott Glenn','director'=>'Jonathan Demme'],
        ['title'=>'Gone Girl','description'=>'A missing wife case turns into a media and marriage nightmare.','year'=>2014,'genre_id'=>7,'age_rating'=>'16','cast'=>'Ben Affleck, Rosamund Pike, Neil Patrick Harris','director'=>'David Fincher'],
        ['title'=>'Zodiac','description'=>'A cartoonist and journalists obsess over an elusive serial killer.','year'=>2007,'genre_id'=>7,'age_rating'=>'16','cast'=>'Jake Gyllenhaal, Robert Downey Jr., Mark Ruffalo','director'=>'David Fincher'],
        ['title'=>'Shutter Island','description'=>'A U.S. marshal investigates a disappearance on a remote asylum island.','year'=>2010,'genre_id'=>7,'age_rating'=>'16','cast'=>'Leonardo DiCaprio, Mark Ruffalo, Ben Kingsley','director'=>'Martin Scorsese'],
        ['title'=>'No Country for Old Men','description'=>'A hunter finds drug money and is pursued by a relentless killer.','year'=>2007,'genre_id'=>7,'age_rating'=>'18','cast'=>'Tommy Lee Jones, Javier Bardem, Josh Brolin','director'=>'Joel Coen, Ethan Coen'],
        ['title'=>'Black Swan','description'=>'A ballerina’s pursuit of perfection fractures her reality.','year'=>2010,'genre_id'=>7,'age_rating'=>'16','cast'=>'Natalie Portman, Mila Kunis, Vincent Cassel','director'=>'Darren Aronofsky'],
    ];

    public function run(): void
    {
        $genres = Genre::all();
        if ($genres->isEmpty()) {
            return; // Para se não houver genres
        }

        $movies = [];
        $now = now();
        $ageRatings = ['0', '3', '6','10', '13', '16', '18'];

        for ($i = 0; $i < 50; $i++) {
            $movie = $this->realMovies[$i % count($this->realMovies)];
            $movies[] = [
                'title'       => $movie['title'],
                'description' => $movie['description'],
                'year'        => $movie['year'],
                'genre_id'    => $movie['genre_id'],
                'price'       => rand(350, 600) / 100,
                'stock'       => rand(1, 10),
                'age_rating'  => $ageRatings[array_rand($ageRatings)],
                'cast'        => $movie['cast'],
                'director'    => $movie['director'],
                'created_at'  => $now,
                'updated_at'  => $now,
            ];
        }
        
        // Insere de uma vez (rápido!)
        Movie::insert($movies);
    }
}
