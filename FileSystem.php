


<?php

class FileSystem
{
  //connects to database
  function connect($servername, $username, $password, $dbname = NULL)
  {
    if ($dbname == NULL)
    {
      $conn = new mysqli($servername, $username, $password);
    }else
    {
      $conn = new mysqli($servername, $username, $password, $dbname);
    }

    // Check connection
    if ($conn->connect_error)
    {
      die("Connection failed: " . $conn->connect_error);
    }
    return $conn;
  }
  //creates a databse using given connection and name
  function createDB($conn, $name)
  {
    $create = "CREATE DATABASE ".$name;
    if ($conn->query($create) === TRUE)
    {
      echo "Database created successfully";
      echo "<br>";
    } else
    {
      echo "Error creating database: " . $conn->error;
      echo "<br>";
    }
  }
  //creates a table using given connection and name
  function createTable($conn,$name)
  {
    $fileTable = "CREATE TABLE ".$name."(
      name VARCHAR(50) NOT NULL PRIMARY KEY,
      filepath VARCHAR(100) NOT NULL
    )";

    if ($conn->query($fileTable) === TRUE)
    {
      echo "Table filesystem created successfully";
    } else
    {
      echo "Error creating table: " . $conn->error;
    }
  }

  //deletes table using given connection and name
  function deleteTable($conn,$name)
  {
    $delTable = "DROP TABLE" .$name;
    if ($conn->query($delTable) === TRUE)
    {
      echo "Table filesystem deleted successfully";
    } else
    {
      echo "Error deleting table: " . $conn->error;
    }
  }
  //finds parents of a given array of file elements
  //is a helper function for the readFile function
  function findParents($farray)
  {
    //first element should be volume so can skip it
    $x = 1;
    while($x != count($farray))
    {
      //checks if the current element has a layer value greater than previous
      if($farray[$x]->layer > $farray[$x-1]->layer)
      {
        //if it is then the previous element is the parent of the current element
        $farray[$x]->parent = $farray[$x-1];
        //this elements parent has been found so we can move onto next
        $x++;
      }else
      {
        //loop backwards till we find parent
        $i = $x;
        do
        {
          $i--;
          //parent is first element before current with lower layer count
          if($farray[$x]->layer > $farray[$i]->layer)
          {
            //parent found can now assign and exit loop
            $farray[$x]->parent = $farray[$i];
            $x++;
            break;
          }

        }while($i != 0);
      }

    }
    return $farray;
  }

  //takes a file location and returns an array of all the files in that txt file
  function readFile($file)
  {
    $filetxt = fopen($file, "r") or die("Unable to open file");
    $farray = array();
    while(!feof($filetxt))
    {


      $name = fgets($filetxt);
      //$layer is how many layers deep the file/folder is so can be found by
      //counting the number of tabs
      $layer = preg_match_all("/\t/",$name);
      //parent will be found later
      array_push($farray,new File($name,NULL,$layer));
    }
    //find parent folders of elements in array
    $farray = self::findParents($farray);
    return $farray;
  }

  //loads a given array of files into given table in database
  function loadData($conn,$files,$name)
  {

    for($i=0;$i<count($files);$i++)
    {
      //prepared statement with bound parameters to prevent injection
      $sqlData = "INSERT INTO " .$name."(name, filepath)
      VALUES (?,?)";
      $sql = $conn->prepare($sqlData);
      $sql->bind_param("ss",$files[$i]->name,$files[$i]->calculatePath());
      $sql->execute();

    }
    $sql->close();


  }

  //searches database for a given string
  function searchDB($search,$conn)
  {
    $sql = "SELECT name,filepath FROM filesystem";
    $result = $conn->query($sql);

    if ($result->num_rows > 0)
    {
      // loop through each row
      while($row = $result->fetch_assoc())
      {
        //find any names that have the search term in them
        if(preg_match("/".$search."/"."i",$row["name"]))
        {
          //print the filepath of the matched name
          echo $row["filepath"];
          echo "<br>";
        }
      }
    }
  }

}



class File {
  public $parent;
  public $name;
  public $layer;
  function __construct($name, $parent, $layer)
  {
    $this->parent = $parent;
    $this->name = $name;
    $this->layer = $layer;

  }
  //calculate path of file
  function calculatePath()
  {
    //start from this file
    $curr = $this;
    $path = "";
    //run until reach the volume (e.g the C drive) as it should be the only one with a null parent
    while($curr->parent != NULL)
    {
      //add the current name to the path
      $path = $curr->name."\\".$path;
      //move current to point to its parent
      $curr = $curr->parent;
    }
    //current should be volume so add it to front to finish path
    $path = $curr->name.$path;
    return $path;
  }

}

?>
